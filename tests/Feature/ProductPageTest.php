<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory;
use App\Support\StoreSettings;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Tests\TestCase;

class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $this->assertContains(config("database.connections.{$connection}.database"), [':memory:', 'leather_store_variant_regression_test']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake(config('store.media_disk'));
        Queue::fake();
        $category = Category::create(['name' => 'Leather jackets', 'slug' => 'leather-jackets', 'is_active' => true]);
        $this->product = Product::create(['category_id' => $category->id, 'name' => 'Classic Biker Jacket', 'slug' => 'classic-biker-jacket', 'description' => "A belted jacket.\nRear buckle detail.", 'is_active' => true]);
    }

    public function test_public_visibility_and_stored_slug(): void
    {
        StoreSettings::setBrandName('Sialkot Fashion');
        $url = route('products.show', $this->product->slug);
        $this->get($url)->assertOk()->assertSee('Classic Biker Jacket | Sialkot Fashion')->assertSee('Rear buckle detail.')->assertDontSee('Admin preview');
        $this->get('/products/not-a-product')->assertNotFound();
        $this->product->update(['is_active' => false]);
        $this->get($url)->assertNotFound();
        $this->product->update(['is_active' => true]);
        $this->product->category->update(['is_active' => false]);
        $this->get($url)->assertNotFound();
        $this->product->category->update(['is_active' => true]);
        $this->product->delete();
        $this->get($url)->assertNotFound();
    }

    public function test_draft_preview_is_authorized_private_and_does_not_publish(): void
    {
        $this->product->update(['is_active' => false]);
        $this->product->category->update(['is_active' => false]);
        $url = route('products.preview', $this->product->slug);
        $this->get($url)->assertForbidden();
        config(['app.env' => 'local']);
        $this->actingAs(User::factory()->create());
        $response = $this->get($url)->assertOk()->assertSee('Admin preview')->assertSee('noindex, nofollow, noarchive')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertFalse($this->product->fresh()->is_active);
        $this->get(route('products.show', $this->product->slug))->assertNotFound();
        $this->product->delete();
        $this->get($url)->assertNotFound();
    }

    public function test_preview_rejects_users_without_panel_or_product_permission(): void
    {
        config(['app.env' => 'production']);
        $this->actingAs(User::factory()->create());
        $url = route('products.preview', $this->product->slug);
        $this->get($url)->assertForbidden();
        config(['app.env' => 'local']);
        $this->actingAs(new DeniedPanelUser);
        $this->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create());
        Gate::policy(Product::class, DeniedProductPolicy::class);
        $this->get($url)->assertForbidden();
    }

    public function test_initial_gallery_order_alt_variants_and_no_photo_color(): void
    {
        $this->product->update(['pkr_price_minor' => 100000]);
        $black = $this->product->colors()->create(['name' => 'Black', 'swatch_hex' => '#111111']);
        $brown = $this->product->colors()->create(['name' => 'Brown', 'swatch_hex' => '#664433']);
        $available = $black->variants()->create(['size' => 'XL', 'sku' => 'CBJ-BLK-XL', 'is_active' => true]);
        app(Inventory::class)->adjust($available->id, 3, 'Gallery selection fixture', null);
        $black->variants()->create(['size' => 'SECRET-SIZE', 'sku' => 'HIDDEN-SKU', 'is_active' => false]);
        $brown->variants()->create(['size' => 'M', 'sku' => 'CBJ-BRN-M', 'is_active' => true]);
        $first = $black->addMedia(UploadedFile::fake()->image('front.jpg', 600, 800))->withCustomProperties(['alt' => 'Front zipper detail'])->toMediaCollection('gallery');
        $second = $black->addMedia(UploadedFile::fake()->image('back.jpg', 600, 800))->withCustomProperties(['alt' => 'Rear buckle detail'])->toMediaCollection('gallery');
        $second->update(['order_column' => 0]);
        $shared = $this->product->addMedia(UploadedFile::fake()->image('measure.jpg'))->withCustomProperties(['alt' => 'Measurement diagram', 'placement' => 'size_guide'])->toMediaCollection('shared');
        $url = route('products.show', $this->product->slug);
        $response = $this->get($url)->assertOk()->assertSee('alt="Rear buckle detail"', false)
            ->assertSee('width="600" height="800"', false)->assertSee('fetchpriority="high"', false)
            ->assertSee('Measurement diagram')->assertDontSee('HIDDEN-SKU')->assertDontSee('SECRET-SIZE');
        $this->assertSame([$second->id, $first->id], array_column($response->viewData('selected')['photos'], 'id'));
        $this->assertSame($second->getUrl(), $response->viewData('selected')['photos'][0]['src']);
        $this->assertSame($second->getUrl(), $response->viewData('selected')['photos'][0]['thumbnail']);
        $response = $this->get($url.'?color='.$brown->id.'&size=XL')->assertOk()->assertSee('No Brown photos yet')->assertSee('CBJ-BRN-M');
        $this->assertSame([], $response->viewData('selected')['photos']);
        $this->assertNull($response->viewData('selectedVariant'));
        $this->assertSame($url, $response->viewData('canonical'));
        $this->assertSame([$shared->id], array_column($response->viewData('guides'), 'id'));
        $response = $this->get($url.'?color='.$black->id.'&size=XL')->assertOk()->assertSee('Selected: Black / XL');
        $this->assertSame('CBJ-BLK-XL', $response->viewData('selectedVariant')['sku']);
        $this->get($url.'?color=999999')->assertOk();
    }

    public function test_completed_conversions_render_responsive_webp_and_escape_text(): void
    {
        $this->product->update(['description' => '<script>alert(1)</script> Real description']);
        $color = $this->product->colors()->create(['name' => 'Black', 'swatch_hex' => 'red;xx']);
        $color->variants()->create(['size' => 'M', 'sku' => 'RESPONSIVE-M', 'is_active' => true]);
        $photo = $color->addMedia(UploadedFile::fake()->image('jacket.jpg', 900, 1200))->withCustomProperties(['alt' => 'Zipper "close-up" <script>'])->toMediaCollection('gallery');
        app()->call([Queue::pushed(PerformConversionsJob::class)->last(), 'handle']);
        $response = $this->get(route('products.show', $this->product->slug))->assertOk();
        $data = $response->viewData('selected')['photos'][0];
        $this->assertStringEndsWith('.webp', $data['src']);
        $this->assertNotEmpty($data['srcset']);
        $response->assertSee('srcset=', false)->assertSee('sizes=', false)
            ->assertSee('Zipper &quot;close-up&quot; &lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('background-color: red', false);
        $this->assertSame($photo->fresh()->getUrl('storefront'), $data['src']);
    }

    public function test_filament_has_a_new_tab_preview_action(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $this->product->update(['is_active' => false]);
        Livewire::test(EditProduct::class, ['record' => $this->product->id])
            ->assertActionExists('preview', fn (Action $action): bool => $action->getUrl() === route('products.preview', $this->product->slug) && $action->shouldOpenUrlInNewTab());
    }
}

class DeniedPanelUser extends User implements FilamentUser
{
    protected $table = 'users';

    public function canAccessPanel(Panel $panel): bool
    {
        return false;
    }
}

class DeniedProductPolicy
{
    public function update(User $user, Product $product): bool
    {
        return false;
    }
}
