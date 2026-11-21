<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\ProductPresentation;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AddProductSizesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsaved_conflicts_custom_codes_and_missing_destination_name(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets']);
        $product = Product::create(['name' => 'Jacket', 'slug' => 'jacket', 'category_id' => $category->id]);
        $color = $product->colors()->create(['name' => 'Black']);
        $path = "colors.record-{$color->id}.variants";
        $page = Livewire::test(EditProduct::class, ['record' => $product->id])->set('data.colors.other', ['name' => 'Brown', 'variants' => ['pending' => ['size' => 'M', 'sku' => 'J-BLK-S', 'is_active' => true]]]);
        $page->mountFormComponentAction($path, 'addSizes')->set('mountedActions.0.data.sizes', ['S', 's', 'XL']);
        $rows = $page->get('mountedActions')[0]['data']['rows'];
        $key = array_key_first($rows);
        $this->assertCount(2, $rows);
        $this->assertStringContainsString('already used', $rows[$key]['conflict']);
        $page->callMountedAction()->assertHasErrors();
        $page->set("mountedActions.0.data.rows.$key.sku", 'MY-CUSTOM-S')->set('mountedActions.0.data.sizes', ['S', 'XL', '42']);
        $this->assertContains('MY-CUSTOM-S', array_column($page->get('mountedActions')[0]['data']['rows'], 'sku'));
        $page->callMountedAction()->assertHasNoErrors()->assertSet('data.colors.other.variants.pending.sku', 'J-BLK-S');
        $page->set('data.colors.empty', ['name' => '', 'variants' => []])->mountFormComponentAction('colors.empty.variants', 'addSizes')->set('mountedActions.0.data.sizes', ['M']);
        $this->assertSame([], $page->get('mountedActions')[0]['data']['rows']);
        $this->assertStringContainsString('destination color name', $page->get('mountedActions')[0]['data']['summary']);
    }

    public function test_failed_save_rolls_back_the_entire_variant_batch(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets']);
        $product = Product::create(['name' => 'Jacket', 'slug' => 'jacket', 'category_id' => $category->id]);
        $color = $product->colors()->create(['name' => 'Black']);
        $path = "colors.record-{$color->id}.variants";
        $page = Livewire::test(EditProduct::class, ['record' => $product->id])->set('data.description', 'Pending description')->mountFormComponentAction($path, 'addSizes')->set('mountedActions.0.data.sizes', ['S', 'XL'])->callMountedAction();
        $fail = true;
        ProductVariant::creating(function ($variant) use (&$fail): void {
            if ($fail && $variant->size === 'XL') {
                throw new \RuntimeException('Simulated second insert failure');
            }
        });
        try {
            $page->call('save');
            $this->fail('Expected simulated failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated second insert failure', $error->getMessage());
        } finally {
            $fail = false;
        }
        $this->assertSame(0, ProductVariant::count());
        $this->assertNull($product->fresh()->description);
        $this->assertCount(2, data_get($page->get('data'), $path));
        $this->assertSame('Pending description', $page->get('data')['description']);
        $page->call('save')->assertHasNoFormErrors();
        $this->assertSame(2, ProductVariant::count());
    }

    protected function beforeRefreshingDatabase(): void
    {
        $c = config('database.default');
        $this->assertContains(config("database.connections.$c.database"), [':memory:', 'leather_store_variant_regression_test']);
    }

    public function test_batch_preview_apply_repeat_copy_conflicts_and_unsaved_edits(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets']);
        $product = Product::create(['name' => 'Classic Biker Jacket', 'slug' => 'classic-biker-jacket', 'category_id' => $category->id]);
        $black = $product->colors()->create(['name' => 'Black']);
        foreach (['M', 'L'] as $size) {
            $black->variants()->create(['size' => $size, 'sku' => 'CBJ-BLK-'.$size]);
        }
        $path = "colors.record-{$black->id}.variants";
        $page = Livewire::test(EditProduct::class, ['record' => $product->id])->set('data.description', 'Keep this unsaved edit');
        $page->mountFormComponentAction($path, 'addSizes')->set('mountedActions.0.data.sizes', ['S', ' m ', 'L', 'XL']);
        $rows = $page->get('mountedActions')[0]['data']['rows'];
        $this->assertSame(['S', 'XL'], array_column($rows, 'size'));
        $page->callMountedAction()->assertHasNoFormErrors()->assertSet('data.description', 'Keep this unsaved edit');
        $this->assertSame(2, ProductVariant::count());
        $this->assertCount(4, data_get($page->get('data'), $path));
        $page->mountFormComponentAction($path, 'addSizes')->set('mountedActions.0.data.sizes', ['S', 'M', 'L', 'XL'])->callMountedAction()->assertHasNoFormErrors();
        $this->assertCount(4, data_get($page->get('data'), $path));
        $page->set('data.colors.brown', ['name' => 'Brown', 'variants' => [], 'color_photos' => []]);
        $brownPath = 'colors.brown.variants';
        $page->mountFormComponentAction($brownPath, 'addSizes')->set('mountedActions.0.data.source', 'record-'.$black->id);
        $rows = $page->get('mountedActions')[0]['data']['rows'];
        $this->assertEqualsCanonicalizing(['M', 'L', 'S', 'XL'], array_column($rows, 'size'));
        $this->assertContains('CBJ-BRN-M', array_column($rows, 'sku'));
        $page->callMountedAction()->call('save')->assertHasNoFormErrors()->assertNotified('Product saved');
        $page->call('save')->assertHasNoFormErrors();
        $this->assertSame(8, ProductVariant::count());
        $this->assertSame('Keep this unsaved edit', $product->fresh()->description);
        $this->assertNull(ProductVariant::where('sku', 'CBJ-BRN-M')->first()->on_hand);
        $this->assertNull(ProductVariant::where('sku', 'CBJ-BRN-M')->first()->pkr_price_minor);
        ProductVariant::where('sku', 'CBJ-BRN-M')->first()->forceFill(['on_hand' => 0])->save();
        $summaries = array_column(ProductPresentation::readiness($product->fresh()), 'text');
        $this->assertContains('Brown · 4 sizes · 4 missing checkout prices · 1 out of stock · 3 stock not configured', $summaries);
        $black->variants()->create(['size' => 'Other', 'sku' => 'CBJ-BLK-42']);
        $page = Livewire::test(EditProduct::class, ['record' => $product->id]);
        $page->mountFormComponentAction($path, 'addSizes')->set('mountedActions.0.data.sizes', ['42']);
        $rows = $page->get('mountedActions')[0]['data']['rows'];
        $key = array_key_first($rows);
        $this->assertStringContainsString('already used', $rows[$key]['conflict']);
        $page->callMountedAction()->assertHasErrors();
        $page->set("mountedActions.0.data.rows.$key.sku", 'CBJ-BLK-42-CUSTOM')->callMountedAction()->assertHasNoErrors();
        $page->call('save')->assertHasNoFormErrors();
        $this->assertDatabaseHas('product_variants', ['size' => '42', 'sku' => 'CBJ-BLK-42-CUSTOM']);
        $page->set('data.description', 'Still unsaved')->mountFormComponentAction($path, 'addSizes')->set('mountedActions.0.data.sizes', ['XXL'])->unmountAction()->assertSet('data.description', 'Still unsaved');
        $this->assertDatabaseMissing('product_variants', ['sku' => 'CBJ-BLK-XXL']);
    }
}
