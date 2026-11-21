<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierSource;
use App\Models\User;
use App\Services\Inventory;
use App\Services\PurchaseReceiving;
use App\Services\Purchasing;
use App\Services\Replenishment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchasingTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    private function fixture(): array
    {
        config(['app.env' => 'local']);
        $this->actingAs(User::factory()->create());
        $category = Category::create(['name' => 'Buying', 'slug' => 'buying', 'is_active' => true]);
        $product = Product::create(['name' => 'Jacket', 'slug' => 'buying-jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 900000]);
        $variant = $product->colors()->create(['name' => 'Black'])->variants()->create(['sku' => 'BUY-M', 'size' => 'M', 'is_active' => true, 'reorder_point' => 5, 'reorder_target' => 12]);
        $supplier = Supplier::factory()->create();
        app(Purchasing::class)->source(['supplier_id' => $supplier->id, 'product_variant_id' => $variant->id, 'pack_size' => 2, 'minimum_quantity' => 4, 'is_preferred' => true, 'quote' => '3500']);
        $order = app(Purchasing::class)->save(['supplier_id' => $supplier->id, 'lines' => [['variant_id' => $variant->id, 'quantity' => 5, 'cost' => '3400']]]);
        $this->assertNull($variant->fresh()->on_hand);
        $order = app(Purchasing::class)->issue($order->id, $order->version);

        return [$variant, $order, $order->lines->first()];
    }

    private function receive(PurchaseOrder $order, array $rows, ?int $original = null): PurchaseReceipt
    {
        $service = app(PurchaseReceiving::class);
        $plan = $service->preview($order->id, PurchaseReceiving::baseline($order), $rows, $original);

        return $service->post($order->id, (string) Str::uuid(), $plan['token'], $rows, 'TEST-NOTE', $original ? 'Fix quantity' : null, $original);
    }

    public function test_duplicate_retry_and_competing_stale_receipt_cannot_add_stock_twice(): void
    {
        [$variant,$order,$line] = $this->fixture();
        $service = app(PurchaseReceiving::class);
        $rows = [$line->id => ['accepted' => 2, 'cost' => '3450']];
        $baseline = PurchaseReceiving::baseline($order);
        $plan = $service->preview($order->id, $baseline, $rows);
        $competing = $service->preview($order->id, $baseline, $rows);
        $key = (string) Str::uuid();
        $first = $service->post($order->id, $key, $plan['token'], $rows);
        $retry = $service->post($order->id, $key, $plan['token'], $rows);
        $this->assertSame($first->id, $retry->id);
        try {
            $service->post($order->id, (string) Str::uuid(), $competing['token'], $rows);
            $this->fail('Stale receipt accepted');
        } catch (ValidationException) {
        }
        $this->assertSame(2, $variant->fresh()->on_hand);
        $this->assertSame(1, PurchaseReceipt::count());
        $this->assertSame(1, StockMovement::whereNotNull('purchase_receipt_line_id')->count());
        $this->assertSame(345000, $first->lines->first()->unit_cost_minor);
        $this->assertSame(350000, SupplierSource::first()->quoted_cost_minor);
        $this->assertSame(900000, $variant->productColor->product->pkr_price_minor);
    }

    public function test_partial_damaged_and_shortfall_balances_only_accept_good_stock(): void
    {
        [$variant,$order,$line] = $this->fixture();
        $this->receive($order, [$line->id => ['accepted' => 2, 'damaged_replace' => 1, 'cost' => '3400']]);
        $this->assertSame(3, $line->fresh()->outstanding);
        $this->assertSame('partially_received', $order->fresh()->status);
        $this->assertSame(2, $variant->fresh()->on_hand);
        try {
            $this->receive($order, [$line->id => ['accepted' => 4]]);
            $this->fail('Over receipt accepted');
        } catch (ValidationException) {
        }
        $this->receive($order, [$line->id => ['accepted' => 2, 'damaged_short' => 1]]);
        $this->assertSame(0, $line->fresh()->outstanding);
        $this->assertSame('short_closed', $order->fresh()->status);
        $this->assertSame(4, $variant->fresh()->on_hand);
        $this->assertSame(2, PurchaseReceipt::count());
    }

    public function test_correction_is_atomic_respects_reservations_and_retains_original(): void
    {
        [$variant,$order,$line] = $this->fixture();
        $original = $this->receive($order, [$line->id => ['accepted' => 5, 'cost' => '3400']]);
        DB::transaction(fn () => app(Inventory::class)->change(ProductVariant::lockForUpdate()->findOrFail($variant->id), 0, 4, 'Customer reservation'));
        try {
            $this->receive($order, [$line->id => ['accepted' => -2, 'cost' => '3400']], $original->id);
            $this->fail('Reserved stock removed');
        } catch (ValidationException) {
        }
        $this->assertSame(1, PurchaseReceipt::count());
        $this->assertSame(5, $line->fresh()->accepted);
        $this->assertSame('completed', $order->fresh()->status);
        $adjustment = $this->receive($order, [$line->id => ['accepted' => -1, 'cost' => '3400']], $original->id);
        $this->assertSame($original->id, $adjustment->correction_of_id);
        $this->assertSame(5, $original->fresh()->lines->first()->accepted);
        $this->assertSame(4, $variant->fresh()->on_hand);
        $this->assertSame(4, $variant->fresh()->reserved);
        $this->assertSame(1, $line->fresh()->outstanding);
        $this->assertSame('partially_received', $order->fresh()->status);
    }

    public function test_reorder_math_counts_incoming_once_excludes_drafts_and_rounds_packs(): void
    {
        [$variant,$order,$line] = $this->fixture();
        $this->receive($order, [$line->id => ['accepted' => 2, 'damaged_replace' => 1]]);
        $row = Replenishment::query()->findOrFail($variant->id);
        $this->assertEquals(2, $row->available_now);
        $this->assertEquals(3, $row->incoming_units);
        $this->assertSame(8, Replenishment::suggestion($row));
        $service = app(Replenishment::class);
        $plan = $service->preview([$variant->id]);
        $key = (string) Str::uuid();
        $ids = $service->createDrafts([$variant->id], $key, $plan['token']);
        $this->assertSame($ids, $service->createDrafts([$variant->id], $key, $plan['token']));
        $this->assertSame(8, PurchaseOrder::findOrFail($ids[0])->lines->first()->quantity);
        $this->assertEquals(3, Replenishment::query()->findOrFail($variant->id)->incoming_units);
        $this->assertNotEmpty(Replenishment::query()->findOrFail($variant->id)->existing_drafts);
        DB::transaction(fn () => app(Inventory::class)->change(ProductVariant::lockForUpdate()->findOrFail($variant->id), 0, 1, 'Reserved once'));
        $this->assertEquals(1, Replenishment::query()->findOrFail($variant->id)->available_now);
        $service->settings([$variant->id],null,null);
        $this->assertNull(Replenishment::query()->find($variant->id));
        $service->settings([$variant->id],0,0);
        $this->assertSame(0,$variant->fresh()->reorder_point);
    }
}
