<?php

namespace Tests\Feature;

use App\Filament\Pages\StoreSettings as SettingsPage;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\StoreSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductWhatsappTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $this->assertContains(config("database.connections.$connection.database"), [':memory:', 'leather_store_variant_regression_test']);
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets', 'is_active' => true]);
        $product = Product::create(['name' => 'Classic Biker Jacket', 'slug' => 'classic-biker-jacket', 'category_id' => $category->id, 'is_active' => true]);
        foreach (['Black' => 'BLK', 'Brown' => 'BRN'] as $name => $code) {
            $color = $product->colors()->create(['name' => $name]);
            $color->variants()->create(['size' => 'M', 'sku' => 'CBJ-'.$code.'-M', 'is_active' => true]);
        }

        return $product;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.url' => 'https://shop.example.test']);
    }

    public function test_blank_number_hides_action(): void
    {
        $product = $this->product();
        $this->get(route('products.show', $product->slug))->assertOk()->assertDontSee('Ask on WhatsApp')->assertDontSee('https://wa.me/');
        StoreSettings::setWhatsappNumber('');
        $this->assertNull(StoreSettings::whatsappNumber());
    }

    public function test_selected_variant_and_all_selection_links_use_actual_values_and_clean_public_url(): void
    {
        StoreSettings::setWhatsappNumber('+923001234567');
        $product = $this->product();
        $black = $product->colors->firstWhere('name', 'Black');
        $response = $this->get(route('products.show', [$product->slug, 'color' => $black->id, 'size' => 'M', 'utm_source' => 'ad']))->assertOk()->assertSee('Ask on WhatsApp');
        $link = $response->viewData('selectedVariant')['whatsapp_url'];
        $this->assertStringStartsWith('https://wa.me/923001234567?text=', $link);
        $this->assertSame("Hello, I'm interested in Classic Biker Jacket in Black, size M (SKU CBJ-BLK-M). Is this available?\nhttps://shop.example.test/products/classic-biker-jacket", rawurldecode(parse_url($link, PHP_URL_QUERY) ? substr(parse_url($link, PHP_URL_QUERY), 5) : ''));
        $response->assertSee('href="'.e($link).'"', false);
        $brown = $response->viewData('colors')->firstWhere('name', 'Brown');
        $this->assertStringContainsString('in Brown, size M (SKU CBJ-BRN-M)', rawurldecode($brown['variants'][0]['whatsapp_url']));
        $this->assertStringNotContainsString('utm_source', $link);
    }

    public function test_no_size_or_invalid_size_omits_variant_from_no_javascript_link(): void
    {
        StoreSettings::setWhatsappNumber('923001234567');
        $product = $this->product();
        foreach (['', '?size=NOT-A-SIZE'] as $query) {
            $response = $this->get(route('products.show', $product->slug).$query)->assertOk();
            $link = $response->viewData('selected')['whatsapp_url'];
            $response->assertSee('href="'.e($link).'"', false);
            $message = rawurldecode($link);
            $this->assertStringContainsString('in Black. Is this available?', $message);
            $this->assertStringNotContainsString('SKU', $message);
            $this->assertStringNotContainsString('size ', $message);
        }
    }

    public function test_draft_preview_contains_no_whatsapp_action_or_selection_urls(): void
    {
        StoreSettings::setWhatsappNumber('923001234567');
        $product = $this->product();
        $product->update(['is_active' => false]);
        config(['app.env' => 'local']);
        $this->actingAs(User::factory()->create());
        $this->get(route('products.preview', [$product->slug, 'size' => 'M']))->assertOk()->assertDontSee('Ask on WhatsApp')->assertDontSee('https://wa.me/')->assertViewHas('whatsappUrl', null);
    }

    public function test_admin_setting_validates_normalizes_and_can_be_cleared(): void
    {
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $page = Livewire::test(SettingsPage::class)->assertSet('whatsappNumber', '');
        foreach (['03001234567', '92300 1234567', 'https://wa.me/923001234567', '+1234567890123456'] as $invalid) {
            $page->set('whatsappNumber', $invalid)->call('save')->assertHasErrors(['whatsappNumber']);
        }
        $page->set('whatsappNumber', '+923001234567')->call('save')->assertHasNoErrors()->assertNotified('Store settings saved');
        $this->assertDatabaseHas('store_settings', ['key' => 'whatsapp_number', 'value' => '923001234567']);
        $page->set('whatsappNumber', '')->call('save')->assertHasNoErrors();
        $this->assertNull(StoreSettings::whatsappNumber());
    }
}
