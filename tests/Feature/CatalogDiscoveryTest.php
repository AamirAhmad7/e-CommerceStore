<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Support\StorefrontAnalytics;
use App\Support\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        StorefrontAnalytics::available();
    }

    private function jacket(string $slug = 'discovery-jacket'): Product
    {
        $category = Category::firstOrCreate(['slug' => 'jackets'], ['name' => 'Leather Jackets', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug, 'is_active' => true, 'pkr_price_minor' => 1000000, 'usd_price_minor' => 10000]);
        foreach (['Black' => ['M' => 0, 'L' => 3], 'Brown' => ['M' => 3]] as $name => $sizes) {
            $color = $product->colors()->create(['name' => $name]);
            foreach ($sizes as $size => $stock) {
                $variant = $color->variants()->create(['size' => $size, 'sku' => $slug.'-'.strtoupper($name).'-'.$size, 'is_active' => true, 'pkr_price_minor' => $name === 'Brown' ? 1200000 : null, 'usd_price_minor' => $name === 'Brown' ? 15000 : null]);
                $variant->forceFill(['on_hand' => $stock, 'reserved' => 0])->save();
            }
        }

        return $product;
    }

    public function test_same_variant_filters_cards_prices_and_reservations(): void
    {
        $product = $this->jacket();
        $this->get('/shop?color[]=Black&size[]=M&in_stock=1')->assertOk()->assertViewHas('products', fn ($p) => $p->total() === 0);
        $page = $this->get('/shop?color[]=Brown&size[]=M&in_stock=1&currency=PKR')->assertOk()->assertViewHas('products', fn ($p) => $p->total() === 1);
        $card = $page->viewData('cards')->first();
        $this->assertSame('Brown', $card['color']->name);
        $this->assertNull($card['photo']);
        $this->assertStringContainsString('size=M', $card['url']);
        $this->assertStringContainsString('color='.$card['color']->id, $card['url']);
        $this->assertSame('PKR 12,000', $card['price']);
        $this->get('/shop?color[]=Black&color[]=Brown&size[]=M&size[]=L&in_stock=1')->assertViewHas('products', fn ($p) => $p->total() === 1);
        $this->get('/shop?color[]=Brown&max=11000')->assertViewHas('products', fn ($p) => $p->total() === 0);
        $this->get('/shop?color[]=Brown&currency=USD&max=140')->assertViewHas('products', fn ($p) => $p->total() === 0);
        $this->get('/shop?color[]=Brown&currency=USD&price_currency=PKR&max=11000')->assertViewHas('filters', fn ($f) => ! isset($f['max']))->assertViewHas('products', fn ($p) => $p->total() === 1);
        $variant = $product->colors()->where('name', 'Brown')->first()->variants()->first();
        $variant->forceFill(['reserved' => 3])->save();
        $this->get('/shop?color[]=Brown&in_stock=1')->assertViewHas('products', fn ($p) => $p->total() === 0);
        $variant->forceFill(['reserved' => 0, 'pkr_price_minor' => 0])->save();
        $this->get('/shop?color[]=Brown&currency=USD&in_stock=1')->assertViewHas('products', fn ($p) => $p->total() === 1);
        $variant->forceFill(['pkr_price_minor' => 1200000, 'usd_price_minor' => 0])->save();
        $this->get('/shop?color[]=Brown&currency=USD&in_stock=1')->assertViewHas('products', fn ($p) => $p->total() === 0);
    }

    public function test_search_public_sku_relevance_and_collection_scope(): void
    {
        $first = $this->jacket();
        $other = $this->jacket('another-jacket');
        $other->update(['description' => 'Discovery Jacket']);
        $this->get('/shop?q=%20DISCOVERY%20%20JACKET%20')->assertOk()->assertViewHas('products', fn ($p) => $p->first()->id === $first->id);
        $page = $this->get('/shop?q=DISCOVERY-JACKET-BLACK-M')->assertOk();
        $card = $page->viewData('cards')->first();
        $this->assertSame('Black', $card['color']->name);
        $this->assertStringContainsString('size=M', $card['url']);
        $this->assertStringContainsString('Currently unavailable', $card['detail']);
        $this->get('/shop?q=discovery-jacket-black-m&in_stock=1')->assertViewHas('products', fn ($p) => $p->total() === 0);
        $this->get('/shop?q=leather%20jackets')->assertViewHas('products', fn ($p) => $p->total() === 2);
        $this->get('/shop?q=brown')->assertViewHas('products', fn ($p) => $p->total() === 2)->assertViewHas('cards', fn ($cards) => $cards->every(fn ($card) => $card['color']->name === 'Brown'));
        $other->update(['pkr_price_minor' => 500000]);
        $this->get('/shop?color[]=Black&sort=price_asc')->assertViewHas('products', fn ($p) => $p->first()->id === $other->id);
        $this->get('/shop?color[]=Black&sort=price_desc')->assertViewHas('products', fn ($p) => $p->first()->id === $first->id);
        $collection = Collection::create(['title' => 'Discovery', 'slug' => 'discovery', 'is_published' => true]);
        $collection->items()->create(['product_id' => $first->id, 'sort_order' => 0]);
        $this->get('/collections/discovery?q=another')->assertOk()->assertViewHas('products', fn ($p) => $p->total() === 0);
        $collection->items()->create(['product_id' => $other->id, 'sort_order' => 1]);
        $this->get('/collections/discovery')->assertViewHas('products', fn ($p) => $p->first()->id === $first->id);
        $this->get('/collections/discovery?sort=newest')->assertViewHas('products', fn ($p) => $p->first()->id === $other->id);
        $first->update(['is_active' => false]);
        $this->get('/shop?q=discovery-jacket-black-m')->assertViewHas('products', fn ($p) => $p->total() === 0);
        $collection->update(['is_published' => false]);
        $this->get('/collections/discovery')->assertNotFound();
    }

    public function test_page_queries_are_bounded_and_links_preserve_choices(): void
    {
        $this->jacket();
        StoreSettings::brandName();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get('/shop?color[]=Brown&size[]=M&in_stock=1')->assertOk();
        $small = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 1; $i <= 12; $i++) {
            $this->jacket('jacket-'.$i);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $page = $this->get('/shop?color[]=Brown&size[]=M&in_stock=1')->assertOk();
        $full = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($small, $full, 'Queries must not grow with card count.');
        $this->assertCount(12, $page->viewData('cards'));
        $this->assertSame(13, $page->viewData('products')->total());
        $this->assertStringContainsString('size%5B0%5D=M', $page->viewData('products')->nextPageUrl());
        $this->get('/shop?color[]=Brown&size[]=M&in_stock=1&page=2')->assertViewHas('cards', fn ($cards) => $cards->count() === 1);
        fwrite(STDOUT, "\nDiscovery queries: 1 card=$small, 12 cards=$full\n");
    }
}
