<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\User;
use App\Services\CommerceSettings;
use App\Support\SizeFit;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FitAndQuickAddTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductColor $black;

    private ProductColor $brown;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame(':memory:', config('database.connections.'.config('database.default').'.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        CommerceSettings::save(['shipping_minor' => 0, 'cod_enabled' => true]);
        Queue::fake();
        Storage::fake(config('store.media_disk'));
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets', 'is_active' => true]);
        $this->product = Product::create(['name' => 'Classic Biker Jacket', 'slug' => 'classic-biker-jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 1000000, 'usd_price_minor' => 12000]);
        foreach (['black' => 'Black', 'brown' => 'Brown'] as $key => $name) {
            $this->$key = $this->product->colors()->create(['name' => $name]);
            foreach (['M' => 4, 'L' => 2, 'XL' => 0] as $size => $stock) {
                $variant = $this->$key->variants()->create(['size' => $size, 'sku' => strtoupper($key).'-'.$size, 'is_active' => true]);
                $variant->forceFill(['on_hand' => $stock, 'reserved' => 0])->save();
            }
        }
    }

    public function test_guide_methods_selected_column_populated_rows_and_legacy_image_remain_readable(): void
    {
        $this->product->update(['fit' => 'Relaxed fit', 'material' => 'Owner leather', 'lining' => 'Owner lining', 'size_guide_mode' => 'override', 'size_guide_override' => ['unit' => 'cm', 'basis' => 'garment', 'measurements' => [
            ['label' => 'Chest', 'method' => 'flat_width', 'values' => [['size' => 'M', 'value' => 53], ['size' => 'L', 'value' => 56]]],
            ['label' => 'Shoulder', 'method' => 'length', 'instruction' => 'Measure between shoulder seams.', 'values' => [['size' => 'M', 'value' => 46]]],
            ['label' => 'Empty measurement', 'method' => 'length', 'values' => []],
        ]]]);
        $image = $this->product->addMedia(UploadedFile::fake()->image('guide.jpg'))->withCustomProperties(['placement' => 'size_guide', 'alt' => 'Owner measurement image'])->toMediaCollection('shared');
        $page = $this->get('/products/classic-biker-jacket?color='.$this->black->id.'&size=M')->assertOk()->assertSee('Size guide', false)->assertSee('Width - jacket laid flat')->assertSee('armpit to armpit')->assertSee('Measure between shoulder seams.')->assertSee('data-measure-value="53"', false)->assertSee('data-guide-size="M"', false)->assertDontSee('Empty measurement');
        $this->assertMatchesRegularExpression('/data-guide-size="M"\s+aria-current="true"/', $page->getContent());
        $this->assertSame(1, substr_count($page->getContent(), 'Relaxed fit'));
        $this->assertSame(1, substr_count($page->getContent(), 'Owner leather'));
        $this->assertSame($image->id, $this->product->getFirstMedia('shared')->id);
        $this->assertSame('', SizeFit::method(['label' => 'Legacy chest'], null));
        $this->assertNull(SizeFit::instruction(['label' => 'Chest'], null));
        $this->assertStringContainsString('around the body', SizeFit::method(['method' => 'circumference'], 'body'));
        $this->product->update(['size_guide_override' => null]);
        $this->get('/products/classic-biker-jacket')->assertOk()->assertSee('Size guide', false)->assertSee('Owner measurement image')->assertDontSee('data-measurements', false);
    }

    public function test_model_details_save_with_color_and_follow_its_photos_without_guessing(): void
    {
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $photo = $this->black->addMedia(UploadedFile::fake()->image('black.jpg'))->toMediaCollection('gallery');
        $brownPhoto = $this->brown->addMedia(UploadedFile::fake()->image('brown.jpg'))->toMediaCollection('gallery');
        $path = 'data.colors.record-'.$this->black->id;
        Livewire::test(EditProduct::class, ['record' => $this->product->id])->set($path.'.model_height_cm', 180)->set($path.'.model_size_worn', 'M')->call('save')->assertHasNoFormErrors();
        $this->brown->update(['model_height_cm' => 175, 'model_size_worn' => 'L']);
        $page = $this->get('/products/classic-biker-jacket?color='.$this->black->id.'&size=M')->assertOk();
        $this->assertSame('Model is 180 cm and wears M', $page->viewData('selected')['model_caption']);
        $other = $this->get('/products/classic-biker-jacket?color='.$this->black->id.'&size=L')->assertOk();
        $this->assertSame($page->viewData('selected')['photos'], $other->viewData('selected')['photos']);
        $brown = $this->get('/products/classic-biker-jacket?color='.$this->brown->id)->assertOk();
        $this->assertSame('Model is 175 cm and wears L', $brown->viewData('selected')['model_caption']);
        $this->assertSame($brownPhoto->id, $brown->viewData('selected')['photos'][0]['id']);
        $this->black->update(['name' => 'Jet Black', 'model_size_worn' => null]);
        $this->assertSame($photo->id, $this->black->fresh()->getFirstMedia('gallery')->id);
        $this->assertNull($this->get('/products/classic-biker-jacket?color='.$this->black->id)->viewData('selected')['model_caption']);
    }

    public function test_choices_are_batch_loaded_private_and_match_product_eligibility_without_galleries(): void
    {
        $missing = $this->black->variants()->create(['size' => 'S', 'sku' => 'no-price', 'is_active' => true, 'pkr_price_minor' => 0]);
        $missing->forceFill(['on_hand' => 4])->save();
        $hidden = $this->black->variants()->create(['size' => 'XXL', 'sku' => 'hidden', 'is_active' => false]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->getJson('/products/classic-biker-jacket/choices?currency=USD')->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private')->assertJsonPath('currency', 'USD');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(4, count($queries));
        $this->assertFalse(collect($queries)->contains(fn ($query) => str_contains($query['query'], 'media')));
        $variants = collect($response->json('colors.0.variants'))->keyBy('size');
        $this->assertTrue($variants['M']['can_buy']);
        $this->assertFalse($variants['XL']['can_buy']);
        $this->assertTrue($variants['S']['can_buy']);
        $this->assertFalse($variants->has('XXL'));
        $this->assertArrayNotHasKey('photos', $response->json('colors.0'));
        $this->product->update(['is_active' => false]);
        $this->getJson('/products/classic-biker-jacket/choices')->assertNotFound();
    }

    public function test_filtered_collection_quick_add_keeps_exact_variant_and_rechecks_stock_and_price(): void
    {
        $collection = Collection::create(['title' => 'Winter Jackets', 'slug' => 'winter-jackets', 'is_published' => true]);
        $collection->items()->create(['product_id' => $this->product->id, 'featured_color_id' => $this->brown->id]);
        $page = $this->get('/collections/winter-jackets?size[]=M')->assertOk()->assertSee('data-quick-color="'.$this->brown->id.'"', false)->assertSee('id="quick-add"', false);
        $variant = $this->brown->variants()->where('size', 'M')->sole();
        $data = ['product_id' => $this->product->id, 'variant_id' => $variant->id, 'quantity' => 2, 'currency' => 'PKR', 'expected_price' => 1000000];
        $this->postJson('/cart', $data)->assertOk()->assertJsonPath('count', 2)->assertJsonPath('message', 'Brown / M added to your cart')->assertSessionHas('cart', [$variant->id => 2])->assertSessionHas('continue_shopping', '/collections/winter-jackets?size[]=M');
        $variant->forceFill(['on_hand' => 2])->save();
        $this->postJson('/cart', $data)->assertUnprocessable()->assertJsonValidationErrors('cart')->assertSessionHas('cart', [$variant->id => 2]);
        $variant->forceFill(['on_hand' => 5, 'pkr_price_minor' => 1100000])->save();
        $this->postJson('/cart', $data)->assertUnprocessable()->assertJsonValidationErrors('cart')->assertSessionHas('cart', [$variant->id => 2]);
        $sold = $this->black->variants()->where('size', 'XL')->sole();
        $this->postJson('/cart', array_replace($data, ['variant_id' => $sold->id]))->assertUnprocessable();
        $this->postJson('/cart', array_replace($data, ['product_id' => 999]))->assertUnprocessable();
        $this->post('/cart', array_replace($data, ['variant_id' => $this->black->variants()->where('size', 'M')->sole()->id]))->assertRedirect(route('cart.show'));
    }
}
