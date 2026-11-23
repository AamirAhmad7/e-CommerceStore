<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class StockBatchAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', config('database.default'));
    }

    private function product(): Product
    {
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $category = Category::create(['name' => 'Batch', 'slug' => 'batch', 'is_active' => true]);
        $product = Product::create(['name' => 'Jacket', 'slug' => 'batch-jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 50000]);
        $color = $product->colors()->create(['name' => 'Black']);
        foreach (['S', 'M', 'L'] as $size) {
            $color->variants()->create(['size' => $size, 'sku' => 'BATCH-'.$size, 'is_active' => true]);
        }

        return $product;
    }

    public function test_batch_is_atomic_and_replay_safe_with_explicit_zero_counts(): void
    {
        $product = $this->product();
        $variants = $product->colors->first()->variants->sortBy('id')->values();
        $inventory = app(Inventory::class);
        $rows = [];
        foreach ($variants as $index => $variant) {
            $rows[$variant->id] = ['snapshot' => $inventory->adjustmentSnapshot($variant->id), 'quantity' => [2, 5, 3][$index]];
        }
        $this->assertSame(10, $inventory->adjustBatch($product, $rows, 'opening', 'Opening'));
        try {
            $inventory->adjustBatch($product, $rows, 'opening', 'Retry');
            $this->fail('Replay accepted');
        } catch (ValidationException) {
            $this->assertSame(3, StockMovement::count());
        }
        foreach ($variants as $variant) {
            $rows[$variant->id] = ['snapshot' => $inventory->adjustmentSnapshot($variant->id), 'quantity' => 1];
        }
        $inventory->adjust($variants->last()->id, 1, 'Concurrent receipt', auth()->id());
        $this->assertEquals(Inventory::readSnapshot($rows[$variants->first()->id]['snapshot']), Inventory::readSnapshot($inventory->adjustmentSnapshot($variants->first()->id)));
        try {
            $inventory->adjustBatch($product, $rows, 'receive', 'Delivery');
            $this->fail('Stale batch accepted');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('BATCH-L', $exception->getMessage());
            $this->assertSame(2, $variants->first()->fresh()->on_hand);
            $this->assertSame(4, StockMovement::count());
        }
        $variant = $variants->first();
        DB::transaction(fn () => $inventory->change($variant->fresh(), 0, 1, 'Reservation'));
        try {
            $inventory->adjustBatch($product, [$variant->id => ['snapshot' => $inventory->adjustmentSnapshot($variant->id), 'quantity' => 0]], 'count', 'Count below reserved');
            $this->fail('Reserved units discarded');
        } catch (ValidationException) {
            $this->assertSame(1, $variant->fresh()->reserved);
            $this->assertSame(2, $variant->fresh()->on_hand);
        }
        DB::transaction(fn () => $inventory->change($variant->fresh(), 0, -1, 'Reservation released'));
        $inventory->adjustBatch($product, [$variant->id => ['snapshot' => $inventory->adjustmentSnapshot($variant->id), 'quantity' => 0]], 'count', 'Empty shelf');
        $this->assertSame(0, $variant->fresh()->on_hand);
    }

    public function test_header_batch_preserves_edits_and_main_save_does_not_repeat_movements(): void
    {
        $product = $this->product();
        $variant = $product->colors->first()->variants->first();
        $page = Livewire::test(EditProduct::class, ['record' => $product->id])
            ->set('data.description', 'Pending description')
            ->mountAction('manageStock')
            ->set('mountedActions.0.data.rows.'.$variant->id.'.quantity', 5)
            ->callMountedAction()
            ->assertHasNoErrors()
            ->assertSet('data.description', 'Pending description')
            ->assertSee('Available: 5');
        $this->assertNull($product->fresh()->description);
        $this->assertSame(1, StockMovement::count());
        $page->call('save')->assertHasNoErrors();
        $path = "colors.record-{$variant->product_color_id}.variants.record-{$variant->id}.stock_summary";
        $page->call('mountAction', 'adjustStock', [], ['schemaComponent' => 'form.'.$path, 'recordKey' => (string) $variant->id])->assertSet('mountedActions.0.name', 'manageStock')->assertSet('mountedActions.0.data.focus', $variant->id);
        $this->assertSame(1, StockMovement::count());
        $page->set('mountedActions.0.data.rows.'.$variant->id.'.quantity', 5)
            ->callMountedAction()->assertNotified('Received 5 units across 1 size.');
        $this->assertSame(10, $variant->fresh()->on_hand);
        $page->mountAction('manageStock')->set('mountedActions.0.data.mode', 'count')
            ->set('mountedActions.0.data.rows.'.$variant->id.'.quantity', 0)
            ->set('mountedActions.0.data.reason', 'Shelf empty');
        app(Inventory::class)->adjust($variant->id, 1, 'Concurrent receipt', auth()->id());
        $page->callMountedAction()->assertNotified('Action could not be completed')
            ->assertSet('mountedActions.0.data.rows.'.$variant->id.'.quantity', 0);
        $this->assertSame(11, $variant->fresh()->on_hand);
    }

    public function test_selector_disables_unavailable_sizes_and_never_substitutes_old_links(): void
    {
        $product = $this->product();
        $variants = $product->colors->first()->variants->sortBy('id')->values();
        app(Inventory::class)->adjust($variants[1]->id, 5, 'Opening', auth()->id());
        $url = route('products.show', $product->slug);
        $response = $this->get($url)->assertOk()->assertSee('Currently unavailable');
        $this->assertSame($variants[1]->id, $response->viewData('selectedVariant')['id']);
        $response = $this->get($url.'?size=S')->assertOk()->assertSee('That size is currently unavailable');
        $this->assertNull($response->viewData('selectedVariant'));
        $this->assertStringNotContainsString('data-size="S"', $response->getContent());
        $product->update(['pkr_price_minor' => null, 'usd_price_minor' => 1000]);
        $this->get($url.'?currency=USD')->assertOk()->assertViewHas('selectedVariant', fn ($v) => $v['id'] === $variants[1]->id);
        $this->withSession(['currency' => 'USD'])->post(route('cart.add'), ['product_id' => $product->id, 'variant_id' => $variants[1]->id, 'quantity' => 1])->assertSessionHasErrors('cart');
    }
}
