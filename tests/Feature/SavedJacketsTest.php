<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedJacketsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['store.pakistan_only' => true]);
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    private function jacket(string $slug): Product
    {
        $category = Category::firstOrCreate(['slug' => 'jackets'], ['name' => 'Jackets', 'is_active' => true]);

        return Product::create(['name' => $slug, 'slug' => $slug, 'category_id' => $category->id, 'is_active' => true, 'material' => 'Leather', 'pkr_price_minor' => 1000000]);
    }

    public function test_save_is_idempotent_and_hidden_products_are_excluded(): void
    {
        $jacket = $this->jacket('saved-biker');
        $this->post('/saved-jackets/saved-biker', ['action' => 'save'])->assertRedirect()->assertSessionHas('saved_jackets', [$jacket->id]);
        $this->post('/saved-jackets/saved-biker', ['action' => 'save'])->assertSessionHas('saved_jackets', [$jacket->id]);
        $this->get('/saved-jackets')->assertOk()->assertSee('saved-biker')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $jacket->update(['is_active' => false]);
        $this->get('/saved-jackets')->assertOk()->assertDontSee('saved-biker');
        $this->post('/saved-jackets/saved-biker', ['action' => 'save'])->assertNotFound();
        $this->post('/saved-jackets/saved-biker', ['action' => 'remove'])->assertSessionHas('saved_jackets', []);
    }

    public function test_comparison_is_bounded_and_uses_current_details(): void
    {
        $ids = collect(range(1, 4))->map(fn ($i) => $this->jacket('compare-'.$i)->id)->all();
        $response = $this->withSession(['saved_jackets' => $ids])->get('/saved-jackets?currency=USD')->assertOk()->assertSee('Leather')->assertSee('Not provided')->assertViewHas('currency', 'PKR');
        $this->assertCount(3, $response->viewData('comparison'));
        $this->assertCount(4, $response->viewData('cards'));
    }

    public function test_recent_views_are_deduplicated_and_can_be_cleared(): void
    {
        $jacket = $this->jacket('recent-biker');
        $this->get('/products/recent-biker')->assertOk()->assertSessionHas('recent_jackets', [$jacket->id]);
        $this->get('/products/recent-biker')->assertOk()->assertSessionHas('recent_jackets', [$jacket->id]);
        $this->get('/saved-jackets')->assertOk()->assertViewHas('recentCards', fn ($cards) => $cards->count() === 1);
        $this->delete('/recent-jackets')->assertRedirect('/saved-jackets')->assertSessionMissing('recent_jackets');
    }

    public function test_comparison_accepts_only_saved_products_and_limits_selection(): void
    {
        $one = $this->jacket('first-jacket');
        $two = $this->jacket('second-jacket');
        $other = $this->jacket('not-saved');
        $this->withSession(['saved_jackets' => [$one->id, $two->id]])
            ->get('/saved-jackets?compare[]='.$two->id.'&compare[]='.$other->id)
            ->assertOk()->assertViewHas('comparison', fn ($cards) => $cards->pluck('product.id')->values()->all() === [$two->id]);
        $this->get('/saved-jackets?compare[]=1&compare[]=2&compare[]=3&compare[]=4')->assertSessionHasErrors('compare');
    }

    public function test_save_for_later_moves_only_the_requested_cart_line(): void
    {
        $product = $this->jacket('later-jacket');
        $color = $product->colors()->create(['name' => 'Black']);
        $variant = $color->variants()->create(['size' => 'M', 'sku' => 'LATER-M', 'is_active' => true]);
        $other = $color->variants()->create(['size' => 'L', 'sku' => 'LATER-L', 'is_active' => true]);
        $this->withSession(['cart' => [$variant->id => 2, $other->id => 1]])
            ->post('/cart/'.$variant->id.'/save-for-later')->assertRedirect('/cart')
            ->assertSessionHas('saved_jackets', [$product->id])->assertSessionHas('cart', [$other->id => 1]);
        $this->post('/cart/'.$variant->id.'/save-for-later')->assertNotFound();
        $this->withSession(['saved_jackets' => range(100, 119)])
            ->post('/cart/'.$other->id.'/save-for-later')->assertSessionHasErrors('saved')
            ->assertSessionHas('cart', [$other->id => 1]);
    }

    public function test_wishlist_limit_and_action_validation(): void
    {
        $this->jacket('limit-jacket');
        $this->withSession(['saved_jackets' => range(100, 119)])->post('/saved-jackets/limit-jacket', ['action' => 'save'])->assertSessionHasErrors('saved');
        $this->post('/saved-jackets/limit-jacket', ['action' => 'invalid'])->assertSessionHasErrors('action');
    }
}
