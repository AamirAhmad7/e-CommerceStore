<?php

namespace Tests\Feature;

use App\Filament\Pages\Homepage as HomepageEditor;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Homepage;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StorefrontSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', config('database.default'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets', 'is_active' => true, 'seo_title' => 'Browse leather jackets', 'seo_description' => 'Jackets by size.']);
        $product = Product::create(['name' => 'Biker Jacket', 'slug' => 'biker', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 1000000, 'usd_price_minor' => 10000, 'seo_title' => 'Biker jackets made for you', 'seo_description' => 'Choose your exact color and size.']);
        $color = $product->colors()->create(['name' => 'Black']);
        $variant = $color->variants()->create(['sku' => 'BIKER-BLACK-M', 'size' => 'M', 'is_active' => true]);
        $variant->forceFill(['on_hand' => 1, 'reserved' => 0])->save();

        return $product;
    }

    public function test_metadata_and_structured_data_use_public_variant_money_and_availability(): void
    {
        $product = $this->product();
        $page = $this->get('/products/biker?size=M')->assertOk()->assertSee('<title>Biker jackets made for you</title>', false)->assertSee('Choose your exact color and size.');
        $node = $page->viewData('structuredData')['@graph'][0];
        $this->assertSame('10000.00', $node['offers'][0]['price']);
        $this->assertSame('PKR', $node['offers'][0]['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $node['offers'][0]['availability']);
        $this->assertArrayNotHasKey('aggregateRating', $node);
        $this->get('/products/biker?currency=USD')->assertViewHas('structuredData', fn ($data) => ! isset($data['@graph'][0]['offers']));
        $variant = $product->variants()->first();
        $variant->forceFill(['reserved' => 1])->save();
        $this->get('/products/biker?currency=PKR')->assertViewHas('structuredData', fn ($data) => $data['@graph'][0]['offers'][0]['availability'] === 'https://schema.org/OutOfStock');
        $variant->update(['pkr_price_minor' => 0]);
        $this->get('/products/biker?currency=PKR')->assertViewHas('structuredData', fn ($data) => ! isset($data['@graph'][0]['offers']));
        $product->update(['is_active' => false]);
        $this->get('/products/biker')->assertNotFound()->assertDontSee('Biker jackets made for you');
    }

    public function test_owner_homepage_seo_stays_draft_until_published_and_other_pages_use_overrides(): void
    {
        config(['app.env' => 'local']);
        $product = $this->product();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        Homepage::create(['key' => 'main', 'published' => ['headline' => 'Published headline'], 'draft' => ['headline' => 'Next headline', 'primary_target' => 'shop']]);
        $editor = Livewire::test(HomepageEditor::class)->set('data.seo_title', 'New search title')->set('data.seo_description', 'New search summary')->call('saveDraft')->assertHasNoErrors();
        $this->get('/')->assertDontSee('New search title');
        $editor->call('publish')->assertHasNoErrors();
        $this->get('/')->assertSee('<title>New search title</title>', false)->assertSee('New search summary');
        $this->get('/products/biker/preview')->assertOk()->assertViewHas('structuredData', null)->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->get('/categories/jackets')->assertSee('<title>Browse leather jackets</title>', false);
        $collection = Collection::create(['title' => 'Bikers', 'slug' => 'bikers', 'is_published' => true, 'seo_title' => 'The biker edit']);
        $collection->items()->create(['product_id' => $product->id]);
        $this->get('/collections/bikers')->assertSee('<title>The biker edit</title>', false);
    }

    public function test_sitemap_crawler_defaults_and_branded_missing_page(): void
    {
        $product = $this->product();
        Collection::create(['title' => 'Secret', 'slug' => 'private-edit', 'is_published' => false]);
        $this->get('/sitemap.xml')->assertOk()->assertSee('/products/biker')->assertSee('<lastmod>', false)->assertDontSee('private-edit')->assertDontSee('/checkout')->assertDontSee('/orders/');
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
        $this->get('/products/biker')->assertSee('name="robots" content="noindex, nofollow"', false);
        config(['app.env' => 'production', 'app.url' => 'https://shop.example.test', 'store.indexing_enabled' => true]);
        $this->get('/robots.txt')->assertSee('Allow: /')->assertSee('https://shop.example.test/sitemap.xml');
        $this->get('/products/biker')->assertDontSee('name="robots" content="noindex', false);
        $this->get('/missing-jacket')->assertNotFound()->assertSee('Search the shop')->assertSee('Page not found');
    }
}
