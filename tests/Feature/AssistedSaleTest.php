<?php

namespace Tests\Feature;

use App\Filament\Resources\AssistedSales\Pages\ManageAssistedSales;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Jobs\OrderNotification;
use App\Models\AssistedSale;
use App\Models\AssistedSaleLink;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AssistedSales;
use App\Services\CommerceSettings;
use App\Services\Inventory;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AssistedSaleTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductVariant $black;

    private ProductVariant $brown;

    private User $owner;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner);
        $category = Category::create(['name' => 'Assisted test', 'slug' => 'assisted-test', 'is_active' => true]);
        $this->product = Product::create(['category_id' => $category->id, 'name' => 'Inquiry Jacket', 'slug' => 'inquiry-jacket', 'is_active' => true, 'pkr_price_minor' => 120000]);
        $this->black = $this->product->colors()->create(['name' => 'Black'])->variants()->create(['sku' => 'INQ-BLK-M', 'size' => 'M', 'is_active' => true]);
        $this->brown = $this->product->colors()->create(['name' => 'Brown'])->variants()->create(['sku' => 'INQ-BRN-L', 'size' => 'L', 'is_active' => true]);
        app(Inventory::class)->adjust($this->black->id, 5, 'Isolated opening', $this->owner->id);
        app(Inventory::class)->adjust($this->brown->id, 3, 'Isolated opening', $this->owner->id);
        CommerceSettings::save(['shipping_minor' => 25000, 'tax_basis_points' => 0, 'cod_enabled' => true, 'bank_enabled' => true, 'bank_instructions' => 'Test transfer only']);
    }

    private function draftData(): array
    {
        return ['source' => 'whatsapp', 'customer_label' => 'Test inquiry', 'internal_note' => 'INTERNAL DO NOT SHARE', 'lines' => [['variant_id' => $this->black->id, 'quantity' => 2]]];
    }

    private function share(): AssistedSaleLink
    {
        $this->actingAs($this->owner);
        $sale = app(AssistedSales::class)->save($this->draftData(), share: true);

        return $sale->links()->sole();
    }

    private function payload(string $key): array
    {
        return ['checkout_key' => $key, 'name' => 'Customer', 'email' => 'customer@example.test', 'phone' => '+923001234567', 'address' => 'Private street address', 'city' => 'Sialkot', 'region' => 'Punjab', 'country' => 'PK', 'payment_method' => 'cod', 'reviewed' => '1'];
    }

    private function review(AssistedSaleLink $link): string
    {
        return $this->post(route('assisted.checkout', $link->token))->assertOk()->viewData('key');
    }

    public function test_conversion_is_once_on_retry_and_two_tabs_and_uses_existing_order_workflow(): void
    {
        $data = $this->draftData();
        $data['lines'] = [['variant_id' => $this->black->id, 'quantity' => 1], ['variant_id' => $this->black->id, 'quantity' => 1]];
        $page = Livewire::test(ManageAssistedSales::class)->callAction('create', $data)->assertHasNoActionErrors();
        $sale = AssistedSale::sole();
        $this->assertSame([$this->black->id => 2], $sale->items);
        $this->assertSame('draft', $sale->status);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $this->black->fresh()->reserved);
        Queue::assertNotPushed(OrderNotification::class);
        $page->callAction(TestAction::make('share')->table($sale), AssistedSales::formData($sale))->assertHasNoActionErrors();
        $link = $sale->links()->sole();
        $page->mountAction(TestAction::make('copy')->table($sale))->assertSet('mountedActions.0.name', 'copy');
        $copyPreview = $page->instance()->getMountedAction()->getModalContent()->render();
        $this->assertStringContainsString('Copy message', $copyPreview);
        $this->assertStringContainsString('INQ-BLK-M', $copyPreview);
        $this->assertStringNotContainsString('INTERNAL DO NOT SHARE', $copyPreview);
        auth()->logout();
        foreach (range(1, 2) as $unused) {
            $this->get($link->url())->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertSee('Black')->assertDontSee('INTERNAL DO NOT SHARE')->assertDontSee('og:');
        }
        $this->assertSame(0, DB::table('checkout_attempts')->count());
        $this->assertSame(0, $this->black->fresh()->reserved);
        $key = $this->review($link);
        $secondKey = $this->review($link);
        $this->post(route('checkout.store'), $this->payload($key))->assertRedirect();
        $order = Order::sole();
        $this->post(route('checkout.store'), $this->payload($key))->assertRedirect(route('orders.show', $order->access_token));
        $this->post(route('checkout.store'), $this->payload($secondKey))->assertRedirect(route('orders.show', $order->access_token));
        $this->assertSame(1, Order::count());
        $this->assertSame(2, $this->black->fresh()->reserved);
        $this->assertSame(1, StockMovement::where('order_id', $order->id)->count());
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame(265000, $order->total_minor);
        $this->assertSame('whatsapp', $order->assistedSale->source);
        $this->assertSame('converted', $sale->fresh()->status);
        Queue::assertPushed(OrderNotification::class, 1);
        $this->assertSame(1, $order->events()->where('event_key', 'confirmation')->count());
        $this->get($link->url())->assertSee('already been ordered')->assertDontSee('Private street address')->assertDontSee($order->access_token);
        $this->app['session']->forget('guest_secret');
        $this->post(route('checkout.store'), $this->payload($secondKey))->assertRedirect($link->url())->assertSessionHasErrors('checkout');
        $this->actingAs($this->owner);
        Livewire::test(ListOrders::class)->assertCanSeeTableRecords([$order])->assertSee('whatsapp');
    }

    public function test_price_and_stock_changes_are_rechecked_before_confirmation(): void
    {
        $link = $this->share();
        $key = $this->review($link);
        $this->product->update(['pkr_price_minor' => 130000]);
        $this->post(route('checkout.store'), $this->payload($key))->assertRedirect($link->url())->assertSessionHasErrors('checkout');
        $this->assertSame(0, Order::count());
        $this->get($link->url())->assertSee('PKR 2,400')->assertSee('PKR 2,600');
        $key = $this->review($link);
        $this->post(route('checkout.store'), $this->payload($key))->assertSessionHasErrors('accept_revised_total')->assertRedirect($link->url());
        $this->assertSame(0, $this->black->fresh()->reserved);
        $this->get($link->url())->assertOk();
        $continued = $this->post(route('assisted.checkout', $link->token))->assertOk()->assertSee('Private street address');
        $key = $continued->viewData('key');
        $this->post(route('checkout.store'), $this->payload($key) + ['accept_revised_total' => 1])->assertRedirect();
        $this->assertSame(260000, Order::sole()->subtotal_minor);
        $second = $this->share();
        $secondKey = $this->review($second);
        app(Inventory::class)->adjust($this->black->id, -2, 'Concurrent physical count', $this->owner->id);
        $this->post(route('checkout.store'), $this->payload($secondKey))->assertSessionHasErrors('checkout')->assertRedirect($second->url());
        $this->get($second->url())->assertSee('INQ-BLK-M (requested 2, available 1)')->assertDontSee('Continue to checkout');
        $this->assertSame('shared', $second->sale->fresh()->status);
        $this->assertSame(1, Order::count());
        $copy = app(AssistedSales::class)->duplicate($second->sale);
        $this->assertSame('draft', $copy->status);
        $this->assertFalse($copy->snapshot[0]['valid']);
        $this->assertSame(0, $copy->links()->count());
        $this->assertNotSame($second->sale->reference, $copy->reference);
    }

    public function test_expired_revoked_and_replaced_links_cannot_convert_or_change_the_shared_basket(): void
    {
        $link = $this->share();
        $key = $this->review($link);
        $data = AssistedSales::formData($link->sale);
        $data['lines'] = [['variant_id' => $this->brown->id, 'quantity' => 1]];
        $sale = app(AssistedSales::class)->save($data, $link->sale, share: true);
        $replacement = $sale->links()->whereNull('revoked_at')->sole();
        $this->get($link->url())->assertSee('revoked or replaced')->assertDontSee('Brown');
        $this->post(route('checkout.store'), $this->payload($key))->assertRedirect($link->url())->assertSessionHasErrors('checkout');
        $this->assertSame([$this->black->id => 2], $link->fresh()->items);
        $this->get($replacement->url())->assertSee('Brown')->assertDontSee('INQ-BLK-M');
        $key = $this->review($replacement);
        $this->travel(73)->hours();
        $this->get($replacement->url())->assertSee('has expired');
        $this->post(route('checkout.store'), $this->payload($key))->assertSessionHasErrors('checkout');
        $this->assertSame('expired', $sale->fresh()->display_status);
        $this->travelBack();
        app(AssistedSales::class)->revoke($sale);
        $this->get($replacement->url())->assertSee('revoked or replaced');
        $this->post(route('checkout.store'), $this->payload($key))->assertSessionHasErrors('checkout');
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $this->black->fresh()->reserved);
        $this->assertSame(0, $this->brown->fresh()->reserved);
        Queue::assertNotPushed(OrderNotification::class);
        config(['app.env' => 'production']);
        $this->get('/admin/assisted-sales')->assertForbidden();
    }

    public function test_personal_cart_survives_assisted_checkout_and_retry(): void
    {
        $link = $this->share();
        auth()->logout();
        $this->post(route('cart.add'), ['product_id' => $this->product->id, 'variant_id' => $this->brown->id, 'quantity' => 1])->assertRedirect();
        $this->get($link->url())->assertSessionHas('cart.'.$this->brown->id, 1);
        $key = $this->review($link);
        $payload = $this->payload($key);
        $payload['payment_method'] = 'bank';
        $this->post(route('checkout.store'), $payload)->assertSessionHas('cart.'.$this->brown->id, 1);
        $order = Order::sole();
        $this->assertSame($this->black->id, $order->lines()->sole()->product_variant_id);
        $this->assertSame(0, $this->brown->fresh()->reserved);
        $this->assertSame('bank', $order->payment_method);
        $this->assertSame('unpaid', $order->payment_status);
        $this->post(route('checkout.store'), $payload)->assertSessionHas('cart.'.$this->brown->id, 1);
        $this->get(route('cart.show'))->assertSee('Brown');
        $this->assertSame(1, Order::count());
    }
}
