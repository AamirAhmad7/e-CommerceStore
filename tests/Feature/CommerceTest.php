<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Jobs\OrderNotification;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\CommerceSettings;
use App\Services\Fulfillment;
use App\Services\Inventory;
use App\Services\Pricing;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CommerceTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected ProductVariant $variant;

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
        Storage::fake('local');
        Storage::fake('public');
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets', 'is_active' => true]);
        $this->product = Product::create(['name' => 'Commerce Jacket', 'slug' => 'commerce-jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 123456, 'usd_price_minor' => 1099]);
        $color = $this->product->colors()->create(['name' => 'Black']);
        $this->variant = $color->variants()->create(['size' => 'S', 'sku' => 'C-BLK-S', 'is_active' => true]);
        app(Inventory::class)->adjust($this->variant->id, 5, 'Test opening stock', null);
        CommerceSettings::save(['shipping_minor' => 25000, 'tax_basis_points' => 0, 'cod_enabled' => true, 'bank_enabled' => true, 'bank_instructions' => 'TEST ONLY: no real transfer', 'expiry_hours' => 1]);
    }

    private function customer(string $method = 'cod'): array
    {
        return ['name' => 'Test Buyer', 'email' => 'buyer@example.test', 'phone' => '+923001234567', 'address' => 'Test street', 'city' => 'Lahore', 'region' => 'Punjab', 'postal_code' => '54000', 'country' => 'PK', 'payment_method' => $method];
    }

    private function review(?array $items = null): array
    {
        $items ??= [$this->variant->id => 1];
        $quote = app(Cart::class)->quote($items, 'PKR');

        return ['items' => $items, 'fingerprint' => Cart::fingerprint($quote, CommerceSettings::totals($quote['subtotal']))];
    }

    private function place(string $method = 'cod'): Order
    {
        return app(Checkout::class)->create(hash('sha256', 'guest'), (string) Str::uuid(), $this->customer($method), $this->review());
    }

    private function invalid(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a business-rule validation failure.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_exact_money_base_override_missing_and_from(): void
    {
        $this->assertSame(123456, Pricing::parse('1234.56', 'PKR'));
        $this->assertSame(10, Pricing::parse('0.10', 'USD'));
        $this->assertSame('USD 0.10', Pricing::format(10, 'USD'));
        $this->invalid(fn () => Pricing::parse('1.001', 'PKR'));
        $this->assertNull(Pricing::parse('', 'USD'));
        $this->assertSame(1099, Pricing::resolve($this->variant, 'USD'));
        $this->variant->update(['usd_price_minor' => 2099]);
        $this->assertSame(2099, Pricing::resolve($this->variant, 'USD'));
        $this->variant->productColor->variants()->create(['size' => 'L', 'sku' => 'C-BLK-L', 'is_active' => true]);
        $this->assertSame('From USD 10.99', Pricing::range($this->product->fresh(['colors.variants']), 'USD'));
        $this->product->update(['pkr_price_minor' => null]);
        $this->assertNull(Pricing::resolve($this->variant->fresh(), 'PKR'));
        $this->invalid(fn () => $this->place());
    }

    public function test_filters_match_color_and_size_on_same_variant_and_hide_drafts(): void
    {
        $brown = $this->product->colors()->create(['name' => 'Brown']);
        $brown->variants()->create(['size' => 'M', 'sku' => 'C-BRN-M', 'is_active' => true]);
        $this->get('/shop?color=Black&size=M')->assertOk()->assertDontSee('Commerce Jacket');
        $this->get('/shop?color=Brown&size=M')->assertOk()->assertSee('Commerce Jacket');
        $this->get('/categories/jackets')->assertOk()->assertSee('Commerce Jacket');
        $this->product->update(['is_active' => false]);
        $this->get('/shop?q=Commerce')->assertOk()->assertDontSee('Commerce Jacket');
        $this->get('/')->assertOk()->assertDontSee('Commerce Jacket');
        $this->post('/cart', ['product_id' => $this->product->id, 'variant_id' => $this->variant->id, 'quantity' => 1])->assertSessionHasErrors('cart');
        $this->product->update(['is_active' => true]);
        $this->product->category->update(['is_active' => false]);
        $this->get('/shop')->assertDontSee('Commerce Jacket');
        $this->get('/categories/jackets')->assertNotFound();
    }

    public function test_cart_uses_session_and_server_prices_and_merge_limits(): void
    {
        $this->post('/cart', ['product_id' => $this->product->id, 'variant_id' => $this->variant->id, 'quantity' => 1, 'price' => 1])->assertRedirect(route('cart.show'));
        $this->post('/cart', ['product_id' => $this->product->id, 'variant_id' => $this->variant->id, 'quantity' => 1])->assertSessionHas('cart.'.$this->variant->id, 2);
        $this->get('/cart')->assertOk()->assertSee('PKR 1234.56')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->patch('/cart/'.$this->variant->id, ['quantity' => 99])->assertSessionHasErrors('cart');
        $this->post('/cart', ['product_id' => $this->product->id, 'variant_id' => 999999, 'quantity' => 1])->assertSessionHasErrors('cart');
        $this->app['session']->flush();
        $this->get('/cart')->assertSee('Your cart is empty.')->assertDontSee('Commerce Jacket');
        $this->patch('/cart/'.$this->variant->id, ['quantity' => 1])->assertNotFound();
        $this->assertSame(0, $this->variant->fresh()->reserved);
    }

    public function test_checkout_idempotency_snapshots_shipping_and_cod_payment(): void
    {
        $key = (string) Str::uuid();
        $review = $this->review();
        $guest = hash('sha256', 'guest');
        $order = app(Checkout::class)->create($guest, $key, $this->customer(), $review);
        $same = app(Checkout::class)->create($guest, $key, $this->customer(), $review);
        $this->assertSame($order->id, $same->id);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, $this->variant->fresh()->reserved);
        $this->invalid(fn () => app(Checkout::class)->create($guest, $key, array_replace($this->customer(), ['city' => 'Karachi']), $review));
        $this->product->update(['name' => 'Changed name', 'pkr_price_minor' => 999]);
        $this->variant->update(['sku' => 'CHANGED-SKU']);
        $this->assertSame('Commerce Jacket', $order->lines()->first()->product_name);
        $this->assertSame(123456, $order->lines()->first()->unit_price_minor);
        $service = app(Fulfillment::class);
        $this->invalid(fn () => $service->transition($order->id, 'shipped'));
        foreach (['confirmed', 'processing', 'shipped', 'shipped', 'delivered'] as $status) {
            $service->transition($order->id, $status);
        }
        $this->assertSame(4, $this->variant->fresh()->on_hand);
        $this->assertSame(0, $this->variant->fresh()->reserved);
        $this->assertSame(3, StockMovement::count());
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->invalid(fn () => $service->transition($order->id, 'cancelled'));
        $user = User::factory()->create();
        $service->verifyPayment($order->id, $user->id, 'Collected COD receipt TEST');
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_multi_line_failure_rolls_back_everything_and_cancellation_is_once(): void
    {
        $second = $this->variant->productColor->variants()->create(['size' => 'L', 'sku' => 'C-L', 'is_active' => true]);
        $this->invalid(fn () => app(Checkout::class)->create(hash('sha256', 'guest'), (string) Str::uuid(), $this->customer(), $this->review([$this->variant->id => 1, $second->id => 1])));
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $this->variant->fresh()->reserved);
        $this->assertSame(1, StockMovement::count());
        $order = $this->place();
        app(Fulfillment::class)->transition($order->id, 'cancelled');
        app(Fulfillment::class)->transition($order->id, 'cancelled');
        $this->assertSame(5, $this->variant->fresh()->on_hand);
        $this->assertSame(0, $this->variant->fresh()->reserved);
        $this->assertSame(3, StockMovement::count());
    }

    public function test_bank_expiry_retries_exclude_cod_and_paid_orders(): void
    {
        $bank = $this->place('bank');
        $cod = $this->place('cod');
        $paid = $this->place('bank');
        $user = User::factory()->create();
        app(Fulfillment::class)->verifyPayment($paid->id, $user->id, 'Independently verified test receipt');
        $this->travel(2)->hours();
        $this->artisan('orders:expire')->assertSuccessful();
        $this->artisan('orders:expire')->assertSuccessful();
        $this->assertSame('expired', $bank->fresh()->status);
        $this->assertSame('pending', $cod->fresh()->status);
        $this->assertSame('pending', $paid->fresh()->status);
        $this->assertSame(2, $this->variant->fresh()->reserved);
        $this->assertSame(5, StockMovement::count());
    }

    public function test_proof_is_private_and_does_not_mark_paid(): void
    {
        $order = $this->place('bank');
        $this->get('/orders/'.$order->id)->assertNotFound();
        $this->get('/orders/'.Str::random(64))->assertNotFound();
        $this->post(route('orders.proof', $order->access_token), ['proof' => UploadedFile::fake()->image('proof.png')])->assertRedirect();
        $order->refresh();
        $this->assertSame('proof_submitted', $order->payment_status);
        Storage::disk('local')->assertExists($order->proof_path);
        $this->get(route('orders.proof.download', $order))->assertForbidden();
        $user = User::factory()->create();
        config(['app.env' => 'production']);
        $this->actingAs($user)->get(route('orders.proof.download', $order))->assertForbidden();
        config(['app.env' => 'local']);
        $this->get(route('orders.proof.download', $order))->assertOk();
        $this->invalid(fn () => app(Fulfillment::class)->transition($order->id, 'processing'));
        app(Fulfillment::class)->verifyPayment($order->id, $user->id, 'Bank ledger verified TEST');
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_shipping_zero_missing_tax_rounding_and_changed_quote(): void
    {
        CommerceSettings::save(['shipping_minor' => null, 'tax_basis_points' => 0]);
        $this->invalid(fn () => CommerceSettings::totals(100));
        CommerceSettings::save(['shipping_minor' => 0, 'tax_basis_points' => null]);
        $this->invalid(fn () => CommerceSettings::totals(100));
        CommerceSettings::save(['shipping_minor' => 0, 'tax_basis_points' => 1250, 'cod_enabled' => true]);
        $this->assertSame(13, CommerceSettings::totals(101)['tax_minor']);
        $this->assertSame(0, CommerceSettings::totals(101)['shipping_minor']);
        $review = $this->review();
        $this->product->update(['pkr_price_minor' => 5555]);
        $this->invalid(fn () => app(Checkout::class)->create(hash('sha256', 'guest'), (string) Str::uuid(), $this->customer(), $review));
        $this->assertSame(0, Order::count());
    }

    public function test_http_checkout_clears_cart_only_after_success_and_retries_return_same_order(): void
    {
        $this->post('/cart', ['product_id' => $this->product->id, 'variant_id' => $this->variant->id, 'quantity' => 1]);
        $response = $this->get('/checkout')->assertOk();
        $key = $response->viewData('key');
        $payload = $this->customer() + ['checkout_key' => $key, 'reviewed' => '1'];
        $response = $this->post('/checkout', $payload)->assertSessionMissing('cart');
        $order = Order::sole();
        $response->assertRedirect(route('orders.show', $order->access_token));
        $this->post('/checkout', $payload)->assertRedirect(route('orders.show', $order->access_token));
        $this->assertSame(1, Order::count());
        $this->get(route('orders.show', $order->access_token))->assertOk()->assertSee($order->number)->assertSee('PKR 1484.56');
    }

    public function test_filament_order_list_detail_and_stock_actions_render(): void
    {
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $order = $this->place();
        Livewire::test(ListOrders::class)->searchTable('Test Buyer')->assertCanSeeTableRecords([$order]);
        Livewire::test(ViewOrder::class, ['record' => $order->id])->assertSee($order->number)->callAction('confirmed')->assertHasNoActionErrors();
        $this->assertSame('confirmed', $order->fresh()->status);
        Livewire::test(\App\Filament\Pages\CommerceSettings::class)->assertSee('Pakistan checkout rules');
    }

    public function test_admin_prices_and_audited_adjustment_and_reserved_stock_floor(): void
    {
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();
        $this->actingAs($user);
        $page = Livewire::test(EditProduct::class, ['record' => $this->product->id]);
        $page->set('data.pkr_price_minor', '1234.57')->call('save')->assertHasNoFormErrors()->call('save')->assertHasNoFormErrors();
        $this->assertSame(123457, (int) $this->product->fresh()->pkr_price_minor);
        $path = 'colors.record-'.$this->variant->product_color_id.'.variants.record-'.$this->variant->id.'.stock_summary';
        $page->callFormComponentAction($path, 'adjustStock', ['quantity' => 2, 'reason' => 'Verified opening count'])->assertHasNoFormErrors();
        $this->assertSame(7, $this->variant->fresh()->on_hand);
        $this->assertSame($user->id, StockMovement::latest('id')->first()->actor_id);
        $order = $this->place();
        $this->invalid(fn () => app(Inventory::class)->adjust($this->variant->id, -7, 'Cannot remove reserved units', $user->id));
        $this->assertSame(7, $this->variant->fresh()->on_hand);
        $this->assertSame(1, $this->variant->fresh()->reserved);
    }

    public function test_tampered_ownership_and_retry_does_not_clear_a_new_cart(): void
    {
        $this->post('/cart', ['product_id' => 999999, 'variant_id' => $this->variant->id, 'quantity' => 1])->assertSessionHasErrors('cart');
        $this->post('/cart', ['product_id' => $this->product->id, 'variant_id' => $this->variant->id, 'quantity' => 1]);
        $key = $this->get('/checkout')->viewData('key');
        $payload = $this->customer() + ['checkout_key' => $key, 'reviewed' => 1];
        $this->post('/checkout',$payload)->assertSessionMissing('cart');
        $this->post('/cart',['product_id' => $this->product->id, 'variant_id' => $this->variant->id, 'quantity' => 1]);
        $this->post('/checkout',$payload)->assertSessionHas('cart.'.$this->variant->id,1);
        $this->assertSame(1,Order::count());
        Queue::assertPushed(OrderNotification::class,fn ($job) => $job->afterCommit === true);
    }
}
