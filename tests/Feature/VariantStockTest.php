<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VariantStockTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $this->assertContains(config("database.connections.$connection.database"), [':memory:', 'leather_store_variant_regression_test']);
    }

    private function variant(): ProductVariant
    {
        $category = Category::create(['name' => 'Stock test', 'slug' => 'stock-test']);
        $product = Product::create(['name' => 'Stock jacket', 'slug' => 'stock-jacket', 'category_id' => $category->id]);

        return $product->colors()->create(['name' => 'Black'])->variants()->create(['size' => 'M', 'sku' => 'STOCK-BLK-M']);
    }

    public function test_opening_receipt_and_count_preserve_reservations_and_reject_replay_and_stale_snapshots(): void
    {
        $variant = $this->variant();
        $inventory = app(Inventory::class);
        $opening = $inventory->adjustmentSnapshot($variant->id);
        $inventory->adjustFromSnapshot($variant->id, $opening, 'opening', 8, 'Opening physical count', null);
        $receipt = $inventory->adjustmentSnapshot($variant->id);
        $inventory->adjustFromSnapshot($variant->id, $receipt, 'receive', 5, 'Delivery received', null);
        $this->assertSame(13, $variant->fresh()->on_hand);
        $this->reject(fn () => $inventory->adjustFromSnapshot($variant->id, $receipt, 'receive', 5, 'Repeated delivery', null));
        $stale = $inventory->adjustmentSnapshot($variant->id);
        DB::transaction(fn () => $inventory->change(ProductVariant::lockForUpdate()->findOrFail($variant->id), 0, 3, 'Test reservation'));
        $this->reject(fn () => $inventory->adjustFromSnapshot($variant->id, $stale, 'count', 12, 'Stale count', null));
        $count = $inventory->adjustmentSnapshot($variant->id);
        $this->reject(fn () => $inventory->adjustFromSnapshot($variant->id, $count, 'count', 2, 'Would discard reservations', null));
        $inventory->adjustFromSnapshot($variant->id, $count, 'count', 12, 'Physical recount', null);
        $this->assertSame(12, $variant->fresh()->on_hand);
        $this->assertSame(3, $variant->fresh()->reserved);
        $this->assertSame(9, Inventory::available($variant->fresh()));
        $this->assertSame(-1, StockMovement::latest('id')->first()->on_hand_delta);
        $this->assertSame(4, StockMovement::count());
        $this->reject(fn () => $inventory->adjustFromSnapshot($variant->id, $count, 'count', 12, 'Replay count', null));
        $this->assertSame(4, StockMovement::count());
    }

    private function reject(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected stock adjustment rejection');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('stock', $exception->errors());
        }
    }
}
