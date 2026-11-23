<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\User;
use App\Services\Inventory;
use App\Services\ProductPhotoPlacement;
use App\Support\ProductPhoto;
use App\Support\ProductPresentation;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Tests\TestCase;

class ProductUsabilityTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function beforeRefreshingDatabase(): void
    {
        $c = config('database.default');
        $this->assertContains(config("database.connections.$c.database"), [':memory:', 'leather_store_variant_regression_test']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Storage::fake(config('store.media_disk'));
        $category = Category::create(['name' => 'Usability', 'slug' => 'usability', 'is_active' => true]);
        $this->product = Product::create(['name' => 'Usability jacket', 'slug' => 'usability-jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 10000]);
    }

    public function test_unavailable_color_is_disabled_without_a_selection_link(): void
    {
        $this->withoutVite();
        $this->color('Black', ['M']);
        $brown = $this->color('Brown', ['M'], 0);
        $response = $this->get('/products/usability-jacket')->assertOk();
        $this->assertMatchesRegularExpression('/<button[^>]*class="color-option color-unavailable"[^>]*disabled/', $response->getContent());
        $response->assertSee('<s>Brown</s>', false)->assertDontSee('data-color="'.$brown->id.'"', false);
    }

    private function color(string $name, array $sizes, int $stock = 3, int $order = 0): ProductColor
    {
        $color = $this->product->colors()->create(['name' => $name, 'sort_order' => $order]);
        foreach ($sizes as $size) {
            $v = $color->variants()->create(['size' => $size, 'sku' => strtoupper($name).'-'.strtoupper($size), 'is_active' => true]);
            app(Inventory::class)->adjust($v->id, $stock, 'Test fixture', null);
        }

        return $color;
    }

    private function photo($owner, string $collection, string $name, ?string $placement = null)
    {
        return $owner->addMedia(UploadedFile::fake()->image($name.'.jpg', 900, 1200))->withCustomProperties(array_filter(['alt' => $name, 'placement' => $placement, 'credit' => 'Preserve me']))->toMediaCollection($collection);
    }

    public function test_default_color_public_preview_direct_links_and_size_order(): void
    {
        $sold = $this->color('Grey', ['M'], 0, 0);
        $brown = $this->color('Brown', [], 3, 1);
        $black = $this->color('Black', ['XL', 's', 'M', 'XS', 'xxl', '42', '38', 'Custom'], 3, 2);
        $navy = $this->color('Navy', ['S'], 3, 3);
        $url = route('products.show', $this->product);
        $response = $this->get($url)->assertOk()->assertDontSee('Brown')->assertSee('Sold out')->assertSee('Choose a size.')->assertDontSee('Price not configured');
        $this->assertSame((string) $black->id, $response->viewData('selected')['id']);
        $this->assertSame(['XS', 'S', 'M', 'XL', 'XXL', '38', '42', 'Custom'], array_column($response->viewData('selected')['variants'], 'label'));
        $this->assertSame('s', $black->variants()->where('sku', 'BLACK-S')->sole()->size);
        $this->assertSame((string) $sold->id, $this->get($url.'?color='.$sold->id)->viewData('selected')['id']);
        $this->get($url.'?color='.$brown->id)->assertOk()->assertSee('That color is not currently offered.')->assertDontSee('No Brown photos');
        $response = $this->get($url.'?color='.$black->id.'&size=s')->assertOk()->assertSee('Selected: Black / S');
        $this->assertSame('BLACK-S', $response->viewData('selectedVariant')['sku']);
        $navy->update(['sort_order' => 1]);
        $this->assertSame((string) $navy->id, $this->get($url)->viewData('selected')['id']);
        config(['app.env' => 'local']);
        $this->actingAs(User::factory()->create());
        $response = $this->get(route('products.preview', $this->product).'?color='.$brown->id)->assertOk()->assertSee('No active sizes (preview only)');
        $this->assertSame((string) $brown->id, $response->viewData('selected')['id']);
    }

    public function test_gallery_classification_never_uses_legacy_or_other_color_as_fallback(): void
    {
        $black = $this->color('Black', ['S', 'M']);
        $empty = $this->color('Green', ['M']);
        $front = $this->photo($black, 'gallery', 'Black front');
        $back = $this->photo($black, 'gallery', 'Black back');
        $detail = $this->photo($this->product, 'shared', 'Neutral lining', 'detail');
        $guide = $this->photo($this->product, 'shared', 'Measurements', 'size_guide');
        $legacy = $this->photo($this->product, 'shared', 'SECRET legacy black image');
        $this->product->update(['size_guide_mode' => 'override', 'size_guide_override' => ['unit' => 'cm', 'basis' => 'garment', 'measurements' => [['label' => 'Demo chest', 'method' => 'flat_width', 'values' => [['size' => 'M', 'value' => 50]]]]]]);
        $response = $this->get(route('products.show', $this->product))->assertOk()->assertSee('Size guide')->assertDontSee('Product-wide images')->assertDontSee('SECRET legacy black image');
        $this->assertSame([$front->id, $back->id], array_column($response->viewData('selected')['photos'], 'id'));
        $this->assertSame([$detail->id], array_column($response->viewData('shared'), 'id'));
        $this->assertSame(1, substr_count($response->getContent(), 'alt="Neutral lining"'));
        $this->assertSame(1, substr_count($response->getContent(), '<summary>Size guide</summary>'));
        $response->assertSee('Product details');
        $this->assertSame([$guide->id], array_column($response->viewData('guides'), 'id'));
        $response = $this->get(route('products.show', $this->product).'?color='.$empty->id)->assertOk()->assertSee('No Green photos yet');
        $response->assertSee('Product details')->assertSee('Demo chest');
        $this->assertSame(1, substr_count($response->getContent(), 'alt="Neutral lining"'));
        $this->assertSame([], $response->viewData('selected')['photos']);
        $this->assertNull($response->viewData('socialPhoto'));
        $this->assertSame($legacy->id, $legacy->fresh()->id);
        $this->get(route('shop', ['color' => 'Green']))->assertOk()->assertSee('Photo coming soon')->assertDontSee('SECRET legacy black image');
    }

    public function test_move_action_preserves_identity_files_alt_responsive_urls_and_next_save(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $black = $this->color('Black', ['M']);
        $front = $this->photo($black, 'gallery', 'Front');
        $shared = $this->photo($this->product, 'shared', 'Black close-up');
        app()->call([Queue::pushed(PerformConversionsJob::class)->last(), 'handle']);
        $shared->refresh();
        $before = ProductPhoto::data($shared);
        $hash = hash_file('sha256', $shared->getPath());
        $uuid = $shared->uuid;
        $page = Livewire::test(EditProduct::class, ['record' => $this->product->id]);
        $page->set('data.description', 'Unsaved description is saved by move')
            ->callAction('moveSharedPhoto', ['photo' => $shared->id, 'color' => $black->id])->assertHasNoActionErrors()->assertNotified('Photo moved; product saved');
        $page->call('save')->assertHasNoFormErrors()->assertNotified('Product saved');
        $moved = $shared->fresh();
        $after = ProductPhoto::data($moved);
        $this->assertSame($before, $after);
        $this->assertSame($uuid, $moved->uuid);
        $this->assertSame($hash, hash_file('sha256', $moved->getPath()));
        $this->assertSame([$front->id, $shared->id], $black->fresh()->getMedia('gallery')->modelKeys());
        $this->assertSame(0, $this->product->fresh()->getMedia('shared')->count());
        $this->assertSame('Preserve me', $moved->getCustomProperty('credit'));
        $this->assertNotNull($after['thumbnail']);
        $this->assertNotEmpty($after['srcset']);
        $this->assertSame('image/webp', getimagesize($moved->getPath('storefront'))['mime']);
        foreach ($moved->responsive_images['storefront']['urls'] as $file) {
            Storage::disk($moved->conversions_disk ?: $moved->disk)->assertExists($moved->id.'/responsive-images/'.$file);
        }
        $this->assertSame('Unsaved description is saved by move', $this->product->fresh()->description);
    }

    public function test_cross_product_move_rejected_and_classification_persists_by_uuid(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $photo = $this->photo($this->product, 'shared', 'Legacy');
        $other = Product::create(['name' => 'Other', 'slug' => 'other', 'category_id' => $this->product->category_id]);
        $foreign = $other->colors()->create(['name' => 'Foreign']);
        try {
            app(ProductPhotoPlacement::class)->moveToColor($this->product, $photo->id, $foreign->id);
            $this->fail('Expected ownership rejection');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        Livewire::test(EditProduct::class, ['record' => $this->product->id])->callFormComponentAction('shared_photos', 'editPhotoAltText', ['alt' => [$photo->uuid => 'Measurement diagram'], 'placement' => [$photo->uuid => 'size_guide']])->assertHasNoFormErrors();
        $this->assertSame('size_guide', $photo->fresh()->getCustomProperty('placement'));
        $this->assertSame('Measurement diagram', $photo->fresh()->getCustomProperty('alt'));
        $this->assertSame($this->product->id, $photo->fresh()->model_id);
    }

    public function test_readiness_distinguishes_configuration_and_stock_and_preview_explains_prices(): void
    {
        $brown = $this->color('Brown', []);
        $black = $this->color('Black', ['M']);
        $grey = $this->color('Grey', ['S'], 0);
        $this->photo($this->product, 'shared', 'Unclassified');
        $this->product->update(['pkr_price_minor' => null]);
        $findings = ProductPresentation::readiness($this->product->fresh());
        $text = implode(' ', array_column($findings, 'text'));
        $this->assertStringContainsString('Brown has no active sizes', $text);
        $this->assertStringContainsString('Black has no color photos', $text);
        $this->assertStringContainsString('No checkout price for Black / M', $text);
        $this->assertStringContainsString('1 shared image needs classification', $text);
        $this->assertContains('Normal stock state', array_column($findings, 'kind'));
        $url = route('products.show', $this->product).'?color='.$black->id.'&size=M';
        $this->get($url)->assertSee('Currently unavailable')->assertDontSee('PKR checkout price is not configured.');
        config(['app.env' => 'local']);
        $this->actingAs(User::factory()->create());
        $this->get(route('products.preview', $this->product).'?color='.$black->id.'&size=M')->assertSee('PKR checkout price is not configured.');
        $this->assertTrue($this->product->fresh()->is_active);
    }

    public function test_saved_rows_collapse_new_rows_open_and_invalid_values_remain(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $color = $this->color('Black', ['S', 'M']);
        $this->photo($color, 'gallery', 'Saved photo');
        $page = Livewire::test(EditProduct::class, ['record' => $this->product->id])->assertSee('At a glance');
        $repeater = $page->instance()->form->getComponentByStatePath('colors');
        $this->assertTrue($repeater->isCollapsed(array_values($repeater->getChildSchemas())[0]));
        $page->callFormComponentAction('colors.record-'.$color->id.'.variants', 'add');
        $newKey = array_key_last(data_get($page->get('data'), 'colors.record-'.$color->id.'.variants'));
        $path = 'data.colors.record-'.$color->id.'.variants.'.$newKey;
        $page->set($path.'.size', 'XL')->set($path.'.sku', 'BLACK-S')->call('save')->assertHasFormErrors()->assertSet($path.'.size', 'XL')->assertSet($path.'.sku', 'BLACK-S');
        $repeater = $page->instance()->form->getComponentByStatePath('colors');
        $this->assertFalse($repeater->isCollapsed(array_values($repeater->getChildSchemas())[0]));
        $this->assertSame(2, $color->variants()->count());
    }

    public function test_pending_conversion_survives_move_and_invalid_form_does_not_move(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $color = $this->color('Black', ['M']);
        $photo = $this->photo($this->product, 'shared', 'Pending move');
        $serialized = serialize(Queue::pushed(PerformConversionsJob::class)->last());
        $page = Livewire::test(EditProduct::class, ['record' => $this->product->id]);
        $page->set('data.name', '')->callAction('moveSharedPhoto', ['photo' => $photo->id, 'color' => $color->id])->assertHasErrors(['data.name' => 'required']);
        $this->assertSame('shared', $photo->fresh()->collection_name);
        app(ProductPhotoPlacement::class)->moveToColor($this->product, $photo->id, $color->id);
        app()->call([unserialize($serialized), 'handle']);
        $this->assertTrue($photo->fresh()->hasGeneratedConversion('storefront'));
        $this->assertNotEmpty(ProductPhoto::data($photo->fresh())['srcset']);
    }

    public function test_color_order_saves_and_normalized_sizes_never_merge_records(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $black = $this->color('Black', ['xxl', '2XL']);
        $green = $this->color('Green', ['M']);
        $page = Livewire::test(EditProduct::class, ['record' => $this->product->id]);
        $state = $page->get('data')['colors'];
        $page->set('data.colors', array_reverse($state, true))->call('save')->assertHasNoFormErrors()->assertNotified('Product saved');
        $this->assertSame([$green->id, $black->id], $this->product->fresh()->colors->modelKeys());
        $response = $this->get(route('products.show', $this->product).'?color='.$black->id)->assertOk();
        $variants = $response->viewData('selected')['variants'];
        $this->assertCount(2, $variants);
        $this->assertNotSame($variants[0]['id'], $variants[1]['id']);
        $this->assertNotSame($variants[0]['label'], $variants[1]['label']);
        $this->assertSame(['xxl', '2XL'], $black->variants()->orderBy('id')->pluck('size')->all());
    }
}
