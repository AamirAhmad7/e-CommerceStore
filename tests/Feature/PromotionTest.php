<?php

namespace Tests\Feature;

use App\Filament\Resources\Promotions\Pages\ManagePromotions;
use App\Filament\Resources\Promotions\Pages\PromotionResults;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\CommerceSettings;
use App\Services\Fulfillment;
use App\Services\Inventory;
use App\Services\OrderCare;
use App\Services\PromotionManagement;
use App\Services\Promotions;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    private function fixture(): array
    {
        Queue::fake();
        $this->withoutVite();
        $category = Category::create(['name' => 'Offer tests', 'slug' => 'offers', 'is_active' => true]);
        $product = Product::create(['name' => 'Jacket', 'slug' => 'offer-jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 999901]);
        $variant = $product->colors()->create(['name' => 'Black'])->variants()->create(['size' => 'M', 'sku' => 'OFFER-M', 'is_active' => true]);
        app(Inventory::class)->adjust($variant->id, 50, 'Isolated test', null);
        $excluded = Product::create(['name' => 'Belt', 'slug' => 'offer-belt', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 50000]);
        $other = $excluded->colors()->create(['name' => 'Tan'])->variants()->create(['size' => 'L', 'sku' => 'OTHER-L', 'is_active' => true]);
        app(Inventory::class)->adjust($other->id, 10, 'Isolated test', null);
        CommerceSettings::save(['shipping_minor' => 30000, 'tax_basis_points' => 0, 'cod_enabled' => true, 'returns_enabled' => true, 'return_days' => 14]);

        return [$variant, $other];
    }

    private function review(array $items, string $code): array
    {
        $quote = app(Cart::class)->quote($items);
        $result = app(Promotions::class)->resolve($quote, CommerceSettings::get(), $code);

        return ['items' => $items, 'promotion_code' => $code, 'fingerprint' => Cart::fingerprint($quote, $result['totals'])];
    }

    private function customer(): array
    {
        return ['name' => 'Demo buyer', 'email' => 'buyer@example.test', 'phone' => '03000000000', 'address' => 'Fictitious', 'city' => 'Sialkot', 'region' => 'Punjab', 'country' => 'PK', 'payment_method' => 'cod'];
    }

    public function test_mixed_basket_cap_allocation_and_batched_eligibility(): void
    {
        [$variant,$other] = $this->fixture();
        $a = Collection::create(['title' => 'Winter', 'slug' => 'winter', 'is_published' => true]);
        $b = Collection::create(['title' => 'Jackets', 'slug' => 'jackets', 'is_published' => true]);
        foreach ([$a, $b] as $c) {
            $c->items()->create(['product_id' => $variant->productColor->product_id]);
        }
        $p = Promotion::factory()->create(['code' => 'WINTER10', 'scope' => 'collections', 'target_ids' => [$a->id, $b->id], 'cap_minor' => 200000, 'minimum_minor' => 1500000]);
        $quote = app(Cart::class)->quote([$variant->id => 3, $other->id => 2]);
        $result = app(Promotions::class)->resolve($quote, CommerceSettings::get(), 'WINTER10');
        $this->assertSame(2999703, $result['snapshot']['eligible_minor']);
        $this->assertSame(3, $result['snapshot']['eligible_units']);
        $this->assertSame(200000, $result['totals']['discount_minor']);
        $this->assertSame(0, $result['lines'][$other->id]['discount']);
        $this->assertSame(2799703, array_sum($result['lines'][$variant->id]['unit_paid']));
        $this->assertSame(200000, array_sum(array_column($result['lines'], 'discount')));
        $this->assertSame(0, $p->fresh()->uses);
        $this->assertSame(0, Order::count());
        config(['app.env' => 'local']);
        $this->actingAs(User::factory()->create());
        $preview = app(PromotionManagement::class)->preview(PromotionManagement::data($p), [$variant->id => 3, $other->id => 2], $p);
        $this->assertSame($result['totals'], $preview['result']['totals']);
        $this->assertSame(0, $p->fresh()->uses);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $page = Livewire::test(ManagePromotions::class)->mountAction(TestAction::make('edit')->table($p))->assertHasNoErrors()->assertActionMounted()->assertSet('mountedActions.0.data.percentage', '10.00');
        $page->set('mountedActions.0.data.preview_items', [['variant_id' => $variant->id, 'quantity' => 3], ['variant_id' => $other->id, 'quantity' => 2]])->callAction(TestAction::make('previewBasket')->schemaComponent())->assertHasNoActionErrors()->assertSet('mountedActions.0.data.basket_preview', fn ($value) => is_string($value) && str_contains($value, 'PKR 2,000'));
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $p->fresh()->uses);
        $allocated = Promotions::allocate(7, [$variant->id => 10, $other->id => 10]);
        $this->assertSame([4, 3], array_values($allocated));
        $this->assertSame(1, array_sum(Promotions::allocate(1, [1 => 999999999900, 2 => 999999999900])));
        $lines = [];
        for ($i = 1; $i <= 24; $i++) {
            $line = $quote['lines'][0];
            $line['id'] = $i;
            $line['quantity'] = 1;
            $line['total'] = $line['price'];
            $lines[] = $line;
        }$large = ['lines' => $lines, 'subtotal' => array_sum(array_column($lines, 'total')), 'currency' => 'PKR', 'valid' => true];
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(Promotions::class)->resolve($large, array_replace(CommerceSettings::defaults(), ['shipping_minor' => 30000, 'tax_basis_points' => 0]), 'WINTER10');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(2, $queries);
    }

    public function test_best_offer_invalid_replacement_and_expiry_require_new_confirmation(): void
    {
        [$variant,$other] = $this->fixture();
        $code = Promotion::factory()->create(['code' => 'SAVE', 'type' => 'fixed', 'amount_minor' => 10000, 'use_limit' => 1]);
        $auto = Promotion::factory()->create(['automatic' => true, 'code' => null, 'type' => 'shipping']);
        $items = [$variant->id => 1];
        $quote = app(Cart::class)->quote($items);
        $r = app(Promotions::class)->resolve($quote, CommerceSettings::get(), 'SAVE');
        $this->assertSame($auto->id, $r['snapshot']['id']);
        $this->assertSame(30000, $r['saving']);
        $auto->update(['status' => 'paused']);
        $this->withSession(['cart' => $items, 'promotion_code' => 'SAVE', 'currency' => 'PKR'])->from(route('cart.show'))->post(route('cart.promotion'), ['code' => ' BAD '])->assertSessionHasErrors('code')->assertSessionHas('promotion_code', 'SAVE')->assertSessionHas('cart', $items);
        $review = $this->review($items, 'SAVE');
        $code->update(['ends_at' => now()->subMinute()]);
        $key = (string) Str::uuid();
        $this->withSession(['cart' => $items, 'promotion_code' => 'SAVE', 'checkout_reviews' => [$key => $review]])->post(route('checkout.store'), $this->customer() + ['checkout_key' => $key, 'reviewed' => 1])->assertRedirect(route('checkout.show'))->assertSessionHasErrors('checkout')->assertSessionHas('cart', $items);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $variant->fresh()->reserved);
        $code->update(['ends_at' => null]);
        $review = $this->review($items, 'SAVE');
        $key = (string) Str::uuid();
        $guest = hash('sha256', 'buyer');
        $order = app(Checkout::class)->create($guest, $key, $this->customer(), $review);
        $retry = app(Checkout::class)->create($guest, $key, $this->customer(), $review);
        $this->assertSame($order->id, $retry->id);
        $this->assertSame(1, $code->fresh()->uses);
        $this->assertDatabaseCount('promotion_redemptions', 1);
        config(['app.env' => 'local']);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(PromotionResults::class, ['record' => $code->id])->assertSee($order->number)->assertSee('PKR 100');
        $this->get(route('promotions.export', $code))->assertOk()->assertDownload('promotion-orders.csv');
    }

    public function test_partial_refund_uses_saved_unit_rounding_and_retains_redemption(): void
    {
        [$variant,$other] = $this->fixture();
        $p = Promotion::factory()->create(['code' => 'ONEPAISA', 'type' => 'fixed', 'amount_minor' => 1, 'scope' => 'variants', 'target_ids' => [$variant->id]]);
        $review = $this->review([$variant->id => 3], 'ONEPAISA');
        $order = app(Checkout::class)->create(hash('sha256', 'buyer'), (string) Str::uuid(), $this->customer(), $review);
        $line = $order->lines()->first();
        $this->assertSame([999900, 999901, 999901], $line->unit_paid_minor);
        $actor = User::factory()->create()->id;
        foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $status) {
            app(Fulfillment::class)->transition($order->id, $status, $actor);
        }app(Fulfillment::class)->verifyPayment($order->id, $actor, 'Test collection');
        $p->update(['amount_minor' => 900000, 'status' => 'archived']);
        $variant->productColor->product->update(['pkr_price_minor' => 2000000]);
        $care = app(OrderCare::class);
        $request = $care->request($order->id, 'return', (string) Str::uuid(), 'Return one', [$line->id => 1]);
        $care->decide($order->id, $request->id, true, $actor, 'Approved');
        $care->receive($order->id, $request->id, [$request->lines()->first()->id => ['resellable' => 1, 'damaged' => 0]], $actor);
        $this->assertSame(999900, Promotions::refundLimit($order->fresh()));
        try {
            $care->refund($order->id, 999901, 'PKR', 'too-much', $actor);
            $this->fail('Refund exceeded paid unit');
        } catch (ValidationException) {
        }
        $care->refund($order->id, 999900, 'PKR', 'unit-one', $actor);
        $care->refund($order->id, 999900, 'PKR', 'unit-one', $actor);
        $this->assertDatabaseCount('order_refunds', 1);
        $this->assertSame(1, $p->fresh()->uses);
        $this->assertSame(1, $order->fresh()->promotion_snapshot['merchandise_discount_minor']);
        $this->assertSame([999900, 999901, 999901], $line->fresh()->unit_paid_minor);
    }
}
