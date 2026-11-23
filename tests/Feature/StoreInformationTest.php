<?php

namespace Tests\Feature;

use App\Filament\Pages\StoreSettings as SettingsPage;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CommerceSettings;
use App\Support\StoreSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StoreInformationTest extends TestCase
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
    }

    public function test_information_pages_footer_and_contact_use_only_configured_details(): void
    {
        foreach (['about-us', 'contact-us', 'help', 'privacy', 'terms'] as $path) {
            $this->get('/'.$path)->assertOk()->assertSee('Customer care')->assertSee(route('information.about'))->assertSee(route('information.contact'));
        }
        $this->get('/contact-us')->assertDontSee('https://wa.me/')->assertSee('Direct contact details have not been published');
        StoreSettings::setWhatsappNumber('+923001234567');
        CommerceSettings::save(['contact_email' => 'owner@example.test']);
        StoreSettings::savePages(['about' => 'Our own story <script>alert(1)</script>', 'hours' => 'Monday to Friday, 10 to 5', 'address' => 'Owner supplied contact address']);
        $this->get('/contact-us')->assertSee('https://wa.me/923001234567')->assertSee('whatsapp-icon')->assertSee('owner@example.test')->assertSee('Monday to Friday')->assertSee('Owner supplied contact address');
        $this->get('/about-us')->assertSee('Our own story')->assertDontSee('<script>alert(1)</script>', false);
        StoreSettings::savePages(['about' => 'Updated story']);
        $this->get('/about-us')->assertSee('Updated story')->assertDontSee('Our own story');
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('information.about'))->assertSee(route('information.faq'));
    }

    public function test_owner_edits_pages_without_a_new_menu_and_errors_preserve_input(): void
    {
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $page = Livewire::test(SettingsPage::class)->set('pages.about', 'A story entered by the owner')->set('brandName', '')->call('save')->assertHasErrors(['brandName'])->assertSet('pages.about', 'A story entered by the owner');
        $page->set('brandName', 'Sialkot Fashion')->call('save')->assertHasNoErrors()->assertNotified('Store settings saved');
        $this->assertSame('A story entered by the owner', StoreSettings::pages()['about']);
    }

    public function test_filters_remain_get_links_and_whatsapp_keeps_exact_product_context(): void
    {
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets', 'is_active' => true]);
        $product = Product::create(['name' => 'Biker', 'slug' => 'biker', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 10000]);
        $color = $product->colors()->create(['name' => 'Black']);
        $variant = $color->variants()->create(['sku' => 'BIK-BLK-M', 'size' => 'M', 'is_active' => true]);
        $variant->forceFill(['on_hand' => 3])->save();
        StoreSettings::setWhatsappNumber('+923001234567');
        $this->get('/shop?size[]=M&in_stock=1')->assertOk()->assertSee('filter-chips')->assertSee('Show results')->assertSee('Reset filters')->assertSee('method="get"', false)->assertSee('aria-label="Remove Size: M"', false)->assertViewHas('cards', fn ($cards) => $cards->count() === 1);
        $response = $this->get('/products/biker?color='.$color->id.'&size=M')->assertOk()->assertSee('id="ask-whatsapp" class="whatsapp-button whatsapp-secondary"', false)->assertSee('whatsapp-icon');
        $this->assertStringContainsString('BIK-BLK-M', $response->viewData('selectedVariant')['whatsapp_url']);
    }
}
