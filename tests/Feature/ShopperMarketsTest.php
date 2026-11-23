<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CommerceSettings;
use App\Services\InternationalInquiry;
use App\Services\Inventory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ShopperMarketsTest extends TestCase
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
        CommerceSettings::save(['shipping_minor' => 25000, 'tax_basis_points' => 0, 'cod_enabled' => true, 'bank_enabled' => true, 'bank_instructions' => 'Test only']);
    }

    private function jacket(string $slug): Product
    {
        $category = Category::firstOrCreate(['slug' => 'jackets'], ['name' => 'Jackets', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => $slug, 'slug' => $slug, 'is_active' => true, 'pkr_price_minor' => 1000000, 'usd_price_minor' => 15000]);
        $color = $product->colors()->create(['name' => 'Black']);
        $variant = $color->variants()->create(['size' => 'M', 'sku' => strtoupper($slug).'-M', 'is_active' => true]);
        app(Inventory::class)->adjust($variant->id, 3, 'Isolated test', null);

        return $product;
    }

    public function test_international_two_jacket_inquiry_uses_usd_and_order_shipping_once(): void
    {
        $first = $this->jacket('first');
        $second = $this->jacket('second');
        $first->update(['pkr_price_minor' => null]);
        $settings = CommerceSettings::get();
        $settings['international_shipping_mode'] = 'separate';
        $settings['international_shipping_rates'] = [['destination' => 'United Kingdom', 'amount' => '25.00', 'basis' => 'order']];
        CommerceSettings::save($settings);
        $this->get('/shop?market=international')->assertOk()->assertSee('USD 150')->assertDontSee('data-quick-add=', false);
        $this->get('/shop?currency=PKR')->assertSee('USD 150');
        $this->get('/shop?in_stock=1&q=first')->assertSee('USD 150');
        $this->get('/products/first')->assertOk()->assertSee('Add to inquiry');
        foreach ([$first, $second] as $product) {
            $this->post('/inquiry/items', ['product_id' => $product->id, 'variant_id' => $product->variants()->sole()->id, 'quantity' => 1])->assertRedirect(route('inquiry.show'));
        }
        $this->post('/inquiry', ['destination' => 'United Kingdom'])->assertRedirect();
        $response = $this->get('/inquiry')->assertOk()->assertSee('USD 325')->assertSee('USD 25')->assertSee('United Kingdom');
        $this->assertStringContainsString('SKU FIRST-M x 1 at USD 150', $response->viewData('message'));
        $this->assertStringContainsString('SKU SECOND-M x 1 at USD 150', $response->viewData('message'));
        $this->get('/checkout')->assertRedirect(route('inquiry.show'));
        $this->post('/cart', ['product_id' => $first->id, 'variant_id' => $first->variants()->sole()->id, 'quantity' => 1])->assertRedirect(route('inquiry.show'));
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $first->variants()->sole()->reserved);
        $settings['international_shipping_mode'] = 'included';
        $this->assertSame(['shipping' => 0, 'total' => 30000, 'wording' => 'Shipping included in the international selling prices.'], InternationalInquiry::shipping(30000, 2, 'United Kingdom', $settings));
        $settings['international_shipping_mode'] = 'separate';
        $this->assertNull(InternationalInquiry::shipping(30000, 2, 'Unknown country', $settings)['total']);
        $settings['international_shipping_rates'][0]['basis'] = 'item';
        $this->assertSame(35000, InternationalInquiry::shipping(30000, 2, 'United Kingdom', $settings)['total']);
    }

    public function test_old_size_link_does_not_select_another_sku_and_inactive_sizes_are_hidden(): void
    {
        $product = $this->jacket('selection');
        $variant = $product->variants()->sole();
        app(Inventory::class)->adjust($variant->id, -3, 'Sold out fixture', null);
        $variant->productColor->variants()->create(['size' => 'L', 'sku' => 'SELECTION-L', 'is_active' => true, 'on_hand' => 5]);
        $variant->productColor->variants()->create(['size' => 'XXM', 'sku' => 'SELECTION-XXM', 'is_active' => false]);
        $response = $this->get('/products/selection?color='.$variant->product_color_id.'&size=M')->assertOk()->assertSee('<span>M</span> <small>Sold out</small>', false)->assertDontSee('SELECTION-XXM');
        $this->assertNull($response->viewData('selectedVariant'));
        $this->assertTrue($response->viewData('sizeUnavailable'));
    }

    public function test_pakistan_stock_failure_keeps_inputs_and_retry_creates_one_order(): void
    {
        $product = $this->jacket('purchase');
        $variant = $product->variants()->sole();
        $this->get('/shop?market=pakistan');
        $this->post('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1])->assertRedirect();
        $key = $this->get('/checkout')->assertOk()->viewData('key');
        $data = ['checkout_key' => $key, 'name' => 'Demo Buyer', 'email' => 'buyer@example.test', 'phone' => '03001234567', 'address' => 'Demo street', 'city' => 'Sialkot', 'region' => 'Punjab', 'country' => 'PK', 'payment_method' => 'cod', 'reviewed' => 1];
        app(Inventory::class)->adjust($variant->id, -3, 'Last units sold fixture', null);
        $this->post('/checkout', $data)->assertRedirect(route('checkout.show'))->assertSessionHasErrors('checkout')->assertSessionHas('checkout_input.name', 'Demo Buyer');
        $this->get('/checkout')->assertOk();
        $this->get('/cart')->assertOk();
        app(Inventory::class)->adjust($variant->id, 3, 'Restocked fixture', null);
        $review = $this->get('/checkout')->assertOk()->assertSee('Demo Buyer')->assertSee('PKR 10,250');
        $data['checkout_key'] = $review->viewData('key');
        $response = $this->post('/checkout', $data)->assertRedirect();
        $this->post('/checkout', $data)->assertRedirect($response->headers->get('Location'));
        $order = Order::sole();
        $this->assertSame('PKR', $order->currency);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame(1025000, $order->total_minor);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee($order->reference);
    }

    public function test_incomplete_configuration_does_not_offer_checkout_to_shoppers(): void
    {
        $product = $this->jacket('address-check');
        $variant = $product->variants()->sole();
        $this->get('/shop?market=pakistan');
        $this->post('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]);
        $this->get('/cart')->assertOk()->assertSee('Proceed to checkout')->assertSee('Update quantity')
            ->assertDontSee('Delivery: Pakistan')->assertDontSee('aria-label="Delivery market"', false);
        CommerceSettings::save(['shipping_minor' => null, 'tax_basis_points' => null, 'cod_enabled' => false, 'bank_enabled' => false]);
        $response = $this->get('/checkout')->assertOk()->assertDontSee('name="address"', false)->assertDontSee('Owner setup required')->assertSee('Contact the store')->assertDontSee('Return to cart')
            ->assertDontSee('Place order /');
        $this->get('/cart')->assertDontSee('Proceed to checkout')->assertSee('Contact the store');
        $this->get('/products/address-check')->assertSee('Contact us to order')->assertDontSee('id="add-to-cart"', false);
        $this->postJson('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1])->assertUnprocessable();
        $this->assertNull($response->viewData('key'));
        $this->assertSame(0, Order::count());
    }

    public function test_quantity_update_can_continue_to_checkout_with_cod_selected(): void
    {
        $product = $this->jacket('quantity-check');
        $variant = $product->variants()->sole();
        CommerceSettings::save(['shipping_minor' => 25000, 'tax_basis_points' => 0, 'cod_enabled' => true, 'bank_enabled' => false]);
        $this->post('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]);
        $this->patch(route('cart.update', $variant->id), ['quantity' => 2, 'after_update' => 'checkout'])->assertRedirect(route('checkout.show'));
        $response = $this->get('/checkout')->assertOk()->assertSee('name="address"', false)->assertSee('Cash on delivery')->assertSee('Pay in cash when your order is delivered.')->assertSee('Payment: Cash on delivery')->assertDontSee('data-header-search', false)->assertDontSee('Shop footer')->assertSee('checkout-region-options')
            ->assertSee('PKR 20,250')->assertDontSee('Pakistan checkout')->assertDontSee('International inquiries');
        $this->assertMatchesRegularExpression('/value="cod"[^>]*checked/', $response->getContent());
        $this->assertSame(0, Order::count());
    }

    public function test_owner_can_choose_free_or_fixed_delivery_without_changing_other_settings(): void
    {
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $page = Livewire::test(\App\Filament\Pages\CommerceSettings::class)->assertSet('deliveryMode', 'fixed');
        $page->set('deliveryMode', 'free')->call('save')->assertHasNoErrors();
        $this->assertSame(0, CommerceSettings::get()['shipping_minor']);
        $this->assertSame(0, CommerceSettings::totals(10000, array_replace(CommerceSettings::get(), ['tax_basis_points' => 1700]))['tax_minor']);
        $this->assertTrue(CommerceSettings::checkoutReady());
        $this->assertTrue(CommerceSettings::get()['cod_enabled']);
        $page->set('deliveryMode', 'fixed')->set('data.shipping_minor', '350')->call('save')->assertHasNoErrors();
        $this->assertSame(35000, CommerceSettings::get()['shipping_minor']);
        $page->set('data.shipping_minor', '')->call('save')->assertHasErrors(['data.shipping_minor']);
        $this->assertSame(35000, CommerceSettings::get()['shipping_minor']);
    }
}
