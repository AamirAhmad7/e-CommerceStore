<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductVariantSaveTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $this->assertContains(config("database.connections.{$connection}.database"), [':memory:', 'leather_store_variant_regression_test']);
    }

    public function test_new_variant_can_be_saved_and_edited_without_refreshing(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Classic Biker Jacket', 'slug' => 'classic-biker-jacket']);
        $color = $product->colors()->create(['name' => 'Black']);
        Storage::fake(config('store.media_disk'));
        $photo = $color->addMedia(UploadedFile::fake()->image('black.jpg'))->toMediaCollection('gallery');
        $color->variants()->create(['size' => 'XL', 'sku' => 'CBJ-BLK-XL']);
        $other = $color->variants()->create(['size' => 'M', 'sku' => 'CBJ-BLK-M']);
        $path = "data.colors.record-{$color->id}.variants";

        $page = Livewire::test(EditProduct::class, ['record' => $product->id])
            ->callFormComponentAction("colors.record-{$color->id}.variants", 'add');
        $newKey = array_key_last(data_get($page->get('data'), "colors.record-{$color->id}.variants"));
        $page->set("{$path}.{$newKey}.size", 'xxxl')
            ->assertSet("{$path}.{$newKey}.sku", 'CBJ-BLK-XXXL')
            ->update(calls: [['method' => 'save', 'params' => [], 'path' => '']], updates: ["{$path}.{$newKey}.sku" => 'CBJ-BLK-XXXL'])
            ->assertHasNoFormErrors()
            ->assertNotified('Product saved');

        $variant = ProductVariant::where('sku', 'CBJ-BLK-XXXL')->sole();
        $page->assertSet("{$path}.record-{$variant->id}.sku", 'CBJ-BLK-XXXL')
            ->set("{$path}.record-{$variant->id}.size", 'XXXL')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Product saved');
        $this->assertSame(3, ProductVariant::count());
        $this->assertSame('XXXL', $variant->fresh()->size);
        $this->assertSame('CBJ-BLK-XXXL', $variant->fresh()->sku);

        $page->set("{$path}.record-{$variant->id}.sku", $other->sku)
            ->assertHasFormErrors(["colors.record-{$color->id}.variants.record-{$variant->id}.sku" => 'unique'])
            ->call('save')
            ->assertHasFormErrors(["colors.record-{$color->id}.variants.record-{$variant->id}.sku" => 'unique']);
        $this->assertSame('CBJ-BLK-XXXL', $variant->fresh()->sku);
        $page->set("{$path}.record-{$variant->id}.sku", 'CBJ-BLK-XXXL')
            ->assertHasNoFormErrors()
            ->call('save')
            ->assertHasNoFormErrors();

        $page->set("{$path}.record-{$variant->id}.sku", 'CBJ-BLK-XXXL-CUSTOM')
            ->assertHasNoFormErrors()
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Product saved');
        $this->assertSame('CBJ-BLK-XXXL-CUSTOM', $variant->fresh()->sku);
        $this->assertSame('CBJ-BLK-M', $other->fresh()->sku);
        $this->assertSame([$photo->id], $color->fresh()->getMedia('gallery')->modelKeys());
        Storage::disk(config('store.media_disk'))->assertExists($photo->getPathRelativeToRoot());

        $page->set('data.colors.new-color', [
            'name' => 'Brown',
            'swatch_hex' => null,
            'color_photos' => [],
            'variants' => ['new-size' => ['size' => 'L', 'sku' => 'CBJ-BRN-L', 'barcode' => null, 'weight_grams' => null, 'is_active' => true]],
        ])->call('save')->assertHasNoFormErrors()->assertNotified('Product saved');
        $this->assertDatabaseHas('product_variants', ['sku' => 'CBJ-BRN-L']);
        $page->call('save')->assertHasNoFormErrors()->assertNotified('Product saved');
        $this->assertSame(4, ProductVariant::count());
    }
}
