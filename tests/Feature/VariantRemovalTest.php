<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class VariantRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame(':memory:', config('database.connections.'.config('database.default').'.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    private function variant(): ProductVariant
    {
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets', 'is_active' => true]);
        $product = Product::create(['name' => 'Jacket', 'slug' => 'jacket', 'category_id' => $category->id]);
        $color = $product->colors()->create(['name' => 'Black']);

        return $color->variants()->create(['size' => 'M', 'sku' => 'J-BLK-M', 'is_active' => true]);
    }

    public function test_unused_size_removal_is_staged_and_unsaved_typos_can_be_removed(): void
    {
        $variant = $this->variant();
        $product = $variant->productColor->product;
        $path = 'colors.record-'.$variant->product_color_id.'.variants';
        $page = Livewire::test(EditProduct::class, ['record' => $product->id])->assertSee('Edit size')->assertSee('Edit color')->assertSee('Delete size');
        $page->set('data.description', 'Keep my pending description')->callFormComponentAction($path, 'delete', [], ['item' => 'record-'.$variant->id]);
        $this->assertNotNull($variant->fresh());
        $page->call('save')->assertHasNoFormErrors();
        $this->assertNull($variant->fresh());
        $this->assertSame('Keep my pending description', $product->fresh()->description);
        $page->callFormComponentAction($path, 'add');
        $key = array_key_last(data_get($page->get('data'), $path));
        $page->set('data.'.$path.'.'.$key.'.size', 'XXM')->callFormComponentAction($path, 'delete', [], ['item' => $key]);
        $this->assertArrayNotHasKey($key, data_get($page->get('data'), $path));
        $page->call('save')->assertHasNoFormErrors();
        $this->assertSame(0, ProductVariant::count());
    }

    public function test_stock_history_blocks_deletion_even_after_staged_removal(): void
    {
        $variant = $this->variant();
        $path = 'colors.record-'.$variant->product_color_id.'.variants';
        $page = Livewire::test(EditProduct::class, ['record' => $variant->productColor->product_id]);
        $page->callFormComponentAction($path, 'delete', [], ['item' => 'record-'.$variant->id]);
        app(Inventory::class)->adjust($variant->id, 2, 'Concurrent receipt fixture', null);
        $page->call('save')->assertHasErrors();
        $this->assertSame(2, $variant->fresh()->on_hand);
        $fresh = Livewire::test(EditProduct::class, ['record' => $variant->productColor->product_id]);
        $fresh->callFormComponentAction($path, 'delete', [], ['item' => 'record-'.$variant->id])->assertNotified('Size cannot be deleted');
        $this->assertArrayHasKey('record-'.$variant->id, data_get($fresh->get('data'), $path));
        $fresh->unmountAction();
        $fresh->set('data.'.$path.'.record-'.$variant->id.'.is_active', false)->call('save')->assertHasNoFormErrors();
        $this->assertFalse($variant->fresh()->is_active);
    }

    public function test_size_removal_saves_when_optional_color_migration_is_pending(): void
    {
        Schema::table('product_colors', function (Blueprint $table): void {
            $table->dropColumn(['model_height_cm', 'model_size_worn']);
        });
        $variant = $this->variant();
        $path = 'colors.record-'.$variant->product_color_id.'.variants';
        Livewire::test(EditProduct::class, ['record' => $variant->productColor->product_id])
            ->callFormComponentAction($path, 'delete', [], ['item' => 'record-'.$variant->id])
            ->call('save')->assertHasNoFormErrors();
        $this->assertNull($variant->fresh());
    }
}
