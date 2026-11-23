<?php

namespace Tests\Feature;

use App\Jobs\OrderNotification;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\CommerceSettings;
use App\Services\Inventory;
use App\Services\Pricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PakistanStorefrontTest extends TestCase
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
        Queue::fake();
        config(['store.pakistan_only' => true]);
        CommerceSettings::save(['shipping_minor' => 50000, 'free_shipping_minor' => 1400000, 'cod_enabled' => true, 'bank_enabled' => true, 'bank_instructions' => 'Test bank']);
    }

    private function jacket(int $stock = 3): Product
    {
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Jacket', 'slug' => 'test-jacket', 'is_active' => true, 'pkr_price_minor' => 1000000, 'usd_price_minor' => 15000, 'delivery_charge_minor' => 25000, 'closure' => 'Zip']);
        $color = $product->colors()->create(['name' => 'Black']);
        $variant = $color->variants()->create(['size' => 'M', 'sku' => 'TEST-M', 'is_active' => true]);
        app(Inventory::class)->adjust($variant->id, $stock, 'Isolated test', null);

        return $product;
    }

    public function test_public_market_stays_pkr_and_uses_whole_price_display(): void
    {
        $this->jacket();
        $this->withSession(['currency' => 'USD', 'delivery_market' => 'international'])->get('/products/test-jacket?currency=USD&market=international')->assertOk()->assertSee('PKR 10,000')->assertDontSee('PKR 10,000.00')->assertDontSee('USD')->assertDontSee('International')->assertSee('Only 3 left')->assertSee('Zip')->assertSee('added-to-cart');
        $this->get('/shop')->assertOk()->assertDontSee('Delivering to')->assertDontSee('market-choice');
        $this->assertSame('PKR 10,000.25', Pricing::format(1000025));
    }

    public function test_last_piece_add_is_rejected_without_changing_cart(): void
    {
        $product = $this->jacket(1);
        $payload = ['product_id' => $product->id, 'variant_id' => $product->variants()->sole()->id, 'quantity' => 1];
        $this->postJson('/cart', $payload)->assertOk()->assertJsonPath('count', 1);
        $this->postJson('/cart', $payload)->assertUnprocessable()->assertJsonValidationErrors('cart')->assertJsonPath('errors.cart.0', "This is the last piece and it's already in your cart.");
        $this->assertSame(1, array_sum(session('cart')));
    }

    public function test_email_optional_cod_order_notes_and_phone_recovery(): void
    {
        config(['store.marketing_enabled' => true, 'store.ga4_id' => 'G-TEST123']);
        $product = $this->jacket();
        $this->postJson('/cart', ['product_id' => $product->id, 'variant_id' => $product->variants()->sole()->id, 'quantity' => 1])->assertOk();
        $review = $this->get('/checkout')->assertOk()->assertSee('Payment: Cash on delivery')->assertDontSee('value="bank"', false)->assertDontSee('data-header-search', false)->assertSee('order_notes');
        $payload = ['checkout_key' => $review->viewData('key'), 'name' => 'Test Buyer', 'phone' => '+923001234567', 'address' => 'Test street', 'city' => 'Sialkot', 'region' => 'Punjab', 'country' => 'PK', 'payment_method' => 'cod', 'reviewed' => 1, 'order_notes' => 'Test instructions'];
        $this->post('/checkout', $payload)->assertRedirect();
        $this->post('/checkout', $payload)->assertRedirect();
        $order = Order::sole();
        $this->assertSame('', $order->customer['email']);
        $this->assertSame('03001234567', $order->customer['phone']);
        $this->assertSame('Test instructions', $order->customer['order_notes']);
        $this->assertSame(25000, $order->shipping_minor);
        $receipt = $this->get(route('orders.received'))->assertOk()->assertSee('commerce-events');
        $event = $receipt->viewData('storefrontEvents')[0];
        $this->assertSame('purchase', $event['event']);
        $this->assertSame(10000, $event['value']);
        $this->assertStringNotContainsString($order->access_token, json_encode($event));
        $this->assertStringNotContainsString('03001234567', json_encode($event));
        Queue::assertNotPushed(OrderNotification::class);
        $this->post('/find-order', ['number' => $order->number, 'contact' => '03009999999'])->assertSessionHasErrors('contact');
        $this->post('/find-order', ['number' => $order->number, 'contact' => '+92 300 1234567'])->assertRedirect(route('orders.show', $order->access_token));
        $this->get(route('orders.show', $order->access_token))->assertOk()->assertSee('Your order')->assertDontSee('id="commerce-events"', false);
    }

    public function test_invalid_mobile_cannot_place_order_and_entered_address_is_preserved(): void
    {
        $product = $this->jacket();
        $this->postJson('/cart', ['product_id' => $product->id, 'variant_id' => $product->variants()->sole()->id, 'quantity' => 1]);
        $review = $this->get('/checkout');
        $this->from('/checkout')->post('/checkout', ['checkout_key' => $review->viewData('key'), 'name' => 'Buyer', 'phone' => '123', 'address' => 'Keep this street', 'city' => 'Sialkot', 'region' => 'Punjab', 'country' => 'PK', 'payment_method' => 'cod', 'reviewed' => 1])->assertSessionHasErrors('phone')->assertSessionHasInput('address', 'Keep this street');
        $this->assertSame(0, Order::count());
    }

    public function test_free_delivery_progress_matches_checkout(): void
    {
        $product = $this->jacket();
        $payload = ['product_id' => $product->id, 'variant_id' => $product->variants()->sole()->id, 'quantity' => 1];
        $this->postJson('/cart', $payload);
        $this->get('/cart')->assertOk()->assertSee('Add PKR 4,000 more for free delivery');
        $this->postJson('/cart', $payload);
        $this->get('/cart')->assertOk()->assertSee('Your order qualifies for free delivery');
        $review = $this->get('/checkout')->assertOk()->assertSee('Free');
        $this->assertSame(0, $review->viewData('totals')['shipping_minor']);
    }

    public function test_cart_cookie_restores_selection_without_trusting_price_or_stock(): void
    {
        $product = $this->jacket();
        $variant = $product->variants()->sole();
        $response = $this->postJson('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2])->assertOk();
        $cookie = $response->getCookie('store_cart')->getValue();
        $this->assertSame([$variant->id => 2], json_decode($cookie, true));
        session()->forget('cart');
        $this->withCookie('store_cart', $cookie)->get('/cart')->assertOk()->assertSee('Test Jacket');
        $this->assertSame(2, session('cart')[$variant->id]);
        app(Inventory::class)->adjust($variant->id, -3, 'Sold elsewhere', null);
        $this->get('/checkout')->assertOk()->assertSee('Review your cart');
    }

    public function test_receipt_requires_the_checkout_session(): void
    {
        $this->get(route('orders.received'))->assertNotFound();
    }
}
