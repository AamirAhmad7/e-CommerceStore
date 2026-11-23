<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchSuggestionsTest extends TestCase
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

    private function jacket(string $slug = 'classic-biker'): Product
    {
        $category = Category::firstOrCreate(['slug' => 'jackets'], ['name' => 'Leather Jackets', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Classic Biker '.$slug, 'slug' => $slug, 'is_active' => true]);
        $color = $product->colors()->create(['name' => 'Black']);
        $color->variants()->create(['size' => 'M', 'sku' => strtoupper($slug).'-BLK-M', 'is_active' => true]);

        return $product;
    }

    public function test_name_color_category_and_partial_sku_match_only_public_products(): void
    {
        $product = $this->jacket();
        $draft = $this->jacket('draft-jacket');
        $draft->update(['is_active' => false]);
        $hidden = $this->jacket('inactive-variant');
        $hidden->variants()->update(['is_active' => false]);
        $deleted = $this->jacket('deleted-jacket');
        $deleted->delete();
        foreach (['classic', 'BLACK', 'leather', 'biker-blk'] as $query) {
            $this->getJson(route('search.suggestions', ['q' => $query, 'currency' => 'USD']))->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.name', $product->name)->assertJsonPath('items.0.url', route('products.show', ['product' => $product->slug, 'currency' => 'USD']));
        }
        $this->get('/shop?q=biker-blk')->assertOk()->assertViewHas('products', fn ($products) => $products->total() === 1);
        $variant = $product->variants()->sole();
        $this->getJson(route('search.suggestions', ['q' => $variant->sku]))->assertJsonPath('items.0.url', route('products.show', ['product' => $product->slug, 'currency' => 'PKR', 'color' => $variant->product_color_id, 'size' => $variant->size]));
        $product->category->update(['is_active' => false]);
        $this->getJson('/shop/suggestions?q=black')->assertJsonCount(0, 'items');
    }

    public function test_suggestions_are_bounded_one_query_and_do_not_change_cart_or_filters(): void
    {
        foreach (range(1, 12) as $index) {
            $this->jacket('jacket-'.$index);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->withSession(['currency' => 'PKR', 'cart' => [99 => 2], 'continue_shopping' => '/shop?color[]=Black'])->getJson('/shop/suggestions?q=black&currency=USD')
            ->assertOk()->assertJsonCount(6, 'items')->assertHeader('Cache-Control', 'no-store, private')
            ->assertSessionHas('currency', 'PKR')->assertSessionHas('cart', [99 => 2])->assertSessionHas('continue_shopping', '/shop?color[]=Black');
        $this->assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();
        $this->getJson('/shop/suggestions?q=b')->assertOk()->assertJsonCount(0, 'items');
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
        $this->getJson('/shop/suggestions?q=%25_')->assertOk()->assertJsonCount(0, 'items');
        $this->getJson('/shop/suggestions?q[]=bad')->assertUnprocessable();
        $this->getJson('/shop/suggestions?q='.str_repeat('a', 161))->assertUnprocessable();
    }

    public function test_shop_and_collection_use_same_sidebar_with_native_search_fallback(): void
    {
        $product = $this->jacket();
        $collection = Collection::create(['title' => 'Winter', 'slug' => 'winter', 'is_published' => true]);
        $collection->items()->create(['product_id' => $product->id]);
        $secondProduct = $this->jacket('classic-second');
        $collection->items()->create(['product_id' => $secondProduct->id]);
        foreach (['/shop', '/collections/winter'] as $url) {
            $this->get($url.'?color[]=Black&q=classic')->assertOk()->assertSee('class="catalog-layout"', false)->assertSee('class="catalog-filters"', false)->assertSee('class="catalog-results"', false)
                ->assertSee('data-search-suggest=', false)->assertDontSee('id="filter-suggestions"', false)->assertSee('name="q" value="classic"', false)->assertSee('form="catalog-filter-form"', false)->assertSee('id="header-suggestions"', false)
                ->assertSee('method="get"', false)->assertSee('aria-label="Remove Color: Black"', false)->assertViewHas('cards', fn ($cards) => $cards->count() === 2);
        }
    }
}
