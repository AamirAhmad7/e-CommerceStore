<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Jobs\OrderNotification;
use App\Livewire\AdminOrderAlerts;
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
use App\Services\OrderCare;
use App\Services\OrderMessages;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class OrderCareTest extends TestCase
{
    use DatabaseMigrations;

    private ProductVariant $variant;

    private User $staff;

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
        $category = Category::create(['name' => 'Care', 'slug' => 'care', 'is_active' => true]);
        $product = Product::create(['name' => 'Saved jacket', 'slug' => 'care-jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 10000]);
        $this->variant = $product->colors()->create(['name' => 'Black'])->variants()->create(['size' => 'M', 'sku' => 'CARE-M', 'is_active' => true]);
        app(Inventory::class)->adjust($this->variant->id, 10, 'Test stock', null);
        $this->staff = User::factory()->create();
        CommerceSettings::save(['shipping_minor' => 2500, 'tax_basis_points' => 0, 'cod_enabled' => true, 'bank_enabled' => true, 'bank_instructions' => 'TEST ONLY', 'returns_enabled' => true, 'return_days' => 14, 'contact_email' => 'care@example.test']);
    }

    private function order(string $method = 'cod', int $quantity = 2): Order
    {
        $items = [$this->variant->id => $quantity];
        $quote = app(Cart::class)->quote($items);

        return app(Checkout::class)->create(hash('sha256', Str::random()), (string) Str::uuid(), ['name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '03000000000', 'address' => 'Test', 'city' => 'Lahore', 'region' => 'Punjab', 'country' => 'PK', 'payment_method' => $method], ['items' => $items, 'fingerprint' => Cart::fingerprint($quote, CommerceSettings::totals($quote['subtotal']))]);
    }

    private function deliver(Order $order): void
    {
        foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $state) {
            app(Fulfillment::class)->transition($order->id, $state, $this->staff->id);
        }
        $order->refresh();
    }

    private function invalid(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected rejection');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_lookup_is_neutral_private_throttled_and_snapshot_receipt_is_immutable(): void
    {
        $order = $this->order();
        $this->get(route('orders.show', str_repeat('a', 64)))->assertNotFound();
        $this->get('/orders/'.$order->id)->assertNotFound();
        $neutral = 'If those details match an order, we will email its private access link. Please check your inbox.';
        foreach ([['number' => $order->number, 'email' => 'wrong@example.test'], ['number' => 'missing', 'email' => 'buyer@example.test'], ['number' => $order->number, 'email' => 'buyer@example.test']] as $data) {
            $this->from('/find-order')->post('/find-order', $data)->assertRedirect('/find-order')->assertSessionHas('status', $neutral);
        }
        $this->assertSame(1, $order->events()->where('event_key', 'like', 'lookup-%')->count());
        $this->variant->update(['size' => 'CHANGED', 'pkr_price_minor' => 90000]);
        $this->variant->productColor->product->update(['name' => 'CHANGED NAME']);
        $body = $order->events()->where('event_key', 'confirmation')->sole()->body;
        $this->assertStringContainsString('Saved jacket / Black / M', $body);
        $this->assertStringContainsString('PKR 225', $body);
        $this->assertStringContainsString('care@example.test', $body);
        $this->assertStringNotContainsString('CHANGED', $body);
        $order->histories()->create(['status' => 'pending', 'payment_status' => 'unpaid', 'note' => 'SECRET STAFF NOTE']);
        $response = $this->get(route('orders.show', $order->access_token))->assertOk()->assertSee('Saved jacket')->assertDontSee('SECRET STAFF NOTE')->assertSee('noindex');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get(route('orders.message', $order->events()->first()))->assertForbidden();
        for ($i = 0; $i < 3; $i++) {
            $this->post('/find-order', ['number' => 'unknown', 'email' => 'nobody@example.test']);
        }
        $this->post('/find-order', ['number' => 'unknown', 'email' => 'nobody@example.test'])->assertStatus(429);
    }

    public function test_request_duplicates_and_ship_before_approval_recheck(): void
    {
        $order = $this->order();
        $key = (string) Str::uuid();
        $care = app(OrderCare::class);
        $r = $care->request($order->id, 'cancellation', $key, 'Changed mind');
        $this->assertSame($r->id, $care->request($order->id, 'cancellation', $key, 'Changed mind')->id);
        $this->invalid(fn () => $care->request($order->id, 'cancellation', $key, 'Different reason'));
        foreach (['confirmed', 'processing'] as $state) {
            app(Fulfillment::class)->transition($order->id, $state, $this->staff->id);
        }
        $this->invalid(fn () => app(Fulfillment::class)->transition($order->id, 'shipped', $this->staff->id));
        $care->decide($order->id, $r->id, false, $this->staff->id, 'Already shipped. Contact us after delivery.', 'PRIVATE NOTE');
        $care->decide($order->id, $r->id, false, $this->staff->id, 'Duplicate');
        app(Fulfillment::class)->transition($order->id, 'shipped', $this->staff->id);
        $this->assertSame(8, $this->variant->fresh()->on_hand);
        $this->assertSame(0, $this->variant->fresh()->reserved);
        $this->assertSame(1, $order->events()->where('event_key', 'decision-'.$r->id)->count());
        $this->get(route('orders.show', $order->access_token))->assertSee('Already shipped.')->assertDontSee('PRIVATE NOTE')->assertDontSee('Request cancellation');
    }

    public function test_admin_alerts_show_pending_orders_and_cancellations(): void
    {
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->staff);
        $order = $this->order();
        $page = Livewire::test(AdminOrderAlerts::class)->assertViewHas('pending', 1)->assertViewHas('cancellations', 0);
        app(OrderCare::class)->request($order->id, 'cancellation', (string) Str::uuid(), 'Changed mind');
        $page->call('$refresh')->assertViewHas('cancellations', 1)->assertSee('Cancellation requests');
        auth()->logout();
        Livewire::test(AdminOrderAlerts::class)->assertForbidden();
    }

    public function test_unpaid_cancellation_releases_once_and_cannot_refund(): void
    {
        $order = $this->order();
        $care = app(OrderCare::class);
        $r = $care->request($order->id, 'cancellation', (string) Str::uuid(), 'Cancel');
        $care->decide($order->id, $r->id, true, $this->staff->id, 'Cancelled before dispatch.');
        $care->decide($order->id, $r->id, true, $this->staff->id, 'Again');
        $this->assertSame(10, $this->variant->fresh()->on_hand);
        $this->assertSame(0, $this->variant->fresh()->reserved);
        $this->assertSame(3, StockMovement::count());
        $this->invalid(fn () => $care->refund($order->id, 1, 'PKR', 'NO-MONEY', $this->staff->id));
        $this->assertDatabaseCount('order_refunds', 0);
    }

    public function test_partial_limits_dispositions_and_refunds_are_separate_and_idempotent(): void
    {
        $order = $this->order();
        $this->deliver($order);
        $care = app(OrderCare::class);
        $line = $order->lines()->sole();
        $r = $care->request($order->id, 'return', (string) Str::uuid(), 'One unit only', [$line->id => 1]);
        $this->invalid(fn () => $care->request($order->id, 'return', (string) Str::uuid(), 'Too many', [$line->id => 2]));
        $other = $this->order();
        $this->invalid(fn () => $care->request($order->id, 'return', (string) Str::uuid(), 'Wrong order', [$other->lines()->sole()->id => 1]));
        $care->decide($order->id, $r->id, true, $this->staff->id, 'Send one unit to the store.');
        $this->assertSame(8, $this->variant->fresh()->on_hand);
        $this->invalid(fn () => $care->receive($order->id, $r->id, [$r->lines()->sole()->id => ['resellable' => 1, 'damaged' => 1]], $this->staff->id));
        $care->receive($order->id, $r->id, [$r->lines()->sole()->id => ['resellable' => 1, 'damaged' => 0]], $this->staff->id);
        $care->receive($order->id, $r->id, [$r->lines()->sole()->id => ['resellable' => 1, 'damaged' => 0]], $this->staff->id);
        $this->assertSame(9, $this->variant->fresh()->on_hand);
        $r2 = $care->request($order->id, 'return', (string) Str::uuid(), 'Second damaged', [$line->id => 1]);
        $care->decide($order->id, $r2->id, true, $this->staff->id, 'Send second unit.');
        $care->receive($order->id, $r2->id, [$r2->lines()->sole()->id => ['resellable' => 0, 'damaged' => 1]], $this->staff->id);
        $this->assertSame(9, $this->variant->fresh()->on_hand);
        $this->assertSame(0, OrderCare::remaining($order, $line->id));
        $this->invalid(fn () => $care->refund($order->id, 100, 'PKR', 'UNPAID', $this->staff->id));
        app(Fulfillment::class)->verifyPayment($order->id, $this->staff->id, 'COD collection');
        $care->refund($order->id, 10000, 'PKR', 'REFUND-1', $this->staff->id);
        $care->refund($order->id, 10000, 'PKR', 'REFUND-1', $this->staff->id);
        $this->invalid(fn () => $care->refund($order->id, 10001, 'PKR', 'REFUND-1', $this->staff->id));
        $this->invalid(fn () => $care->refund($order->id, 12501, 'PKR', 'OVER', $this->staff->id));
        $care->refund($order->id, 12500, 'PKR', 'REFUND-2', $this->staff->id);
        $this->assertDatabaseCount('order_refunds', 2);
        $this->assertDatabaseCount('return_receipts', 2);
        $this->assertSame(22500, (int) DB::table('order_refunds')->sum('amount_minor'));
        $this->assertSame(22500, $order->fresh()->total_minor);
    }

    public function test_return_policy_off_expired_and_rejections_release_allowance(): void
    {
        $order = $this->order();
        $this->deliver($order);
        $care = app(OrderCare::class);
        $line = $order->lines()->sole();
        CommerceSettings::save(array_replace(CommerceSettings::get(), ['returns_enabled' => false]));
        $this->invalid(fn () => $care->request($order->id, 'return', (string) Str::uuid(), 'Disabled', [$line->id => 1]));
        $this->get(route('orders.show', $order->access_token))->assertDontSee('Request a return');
        CommerceSettings::save(array_replace(CommerceSettings::get(), ['returns_enabled' => true]));
        $r = $care->request($order->id, 'return', (string) Str::uuid(), 'Return', [$line->id => 2]);
        $this->invalid(fn () => $care->decide($order->id, $r->id, false, $this->staff->id, ''));
        $care->decide($order->id, $r->id, false, $this->staff->id, 'Not eligible. Please contact us.');
        $this->assertSame(2, OrderCare::remaining($order, $line->id));
        $this->travel(15)->days();
        $this->invalid(fn () => $care->request($order->id, 'return', (string) Str::uuid(), 'Late', [$line->id => 1]));
        $this->travelBack();
    }

    public function test_emails_wait_for_commit_rollback_and_retry_without_business_mutation(): void
    {
        $order = $this->order();
        Queue::swap(app('queue'));
        // Use a real database queue to prove the job is absent until outer commit.
        app()->forgetInstance('queue');
        Queue::clearResolvedInstance('queue');
        DB::beginTransaction();
        OrderMessages::record($order, 'commit-test', 'Test', 'Committed event');
        $this->assertDatabaseCount('jobs', 0);
        DB::commit();
        $this->assertDatabaseCount('jobs', 1);
        DB::beginTransaction();
        OrderMessages::record($order, 'rollback-test', 'Test', 'Rolled back');
        DB::rollBack();
        $this->assertDatabaseMissing('order_events', ['event_key' => 'rollback-test']);
        $this->assertDatabaseCount('jobs', 1);
        $event = $order->events()->where('event_key', 'commit-test')->sole();
        $mailer = \Mockery::mock();
        $mailer->shouldReceive('raw')->once()->andThrow(new \RuntimeException('Test transport failure'));
        Mail::shouldReceive('mailer')->once()->andReturn($mailer);
        try {
            (new OrderNotification($order->id, $event->id))->handle();
            $this->fail('Expected transport failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Test transport failure', $e->getMessage());
        }
        $this->assertSame('Test transport failure', $event->fresh()->last_error);
        $mailer = \Mockery::mock();
        $mailer->shouldReceive('raw')->once();
        Mail::shouldReceive('mailer')->once()->andReturn($mailer);
        (new OrderNotification($order->id, $event->id))->handle();
        (new OrderNotification($order->id, $event->id))->handle();
        $this->assertNotNull($event->fresh()->sent_at);
        $this->assertSame(2, $event->fresh()->attempts);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(2, StockMovement::count());
    }

    public function test_filament_requests_attention_review_and_receipt_actions(): void
    {
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->staff);
        $order = $this->order();
        $this->deliver($order);
        $r = app(OrderCare::class)->request($order->id, 'return', (string) Str::uuid(), 'Return one', [$order->lines()->sole()->id => 1]);
        Livewire::test(ListOrders::class)->filterTable('needs_attention')->assertCanSeeTableRecords([$order]);
        $page = Livewire::test(ViewOrder::class, ['record' => $order->id])->assertSee('Purchased: 2; requested: 1');
        $page->callAction(TestAction::make('approveRequest'.$r->id)->schemaComponent(), ['explanation' => 'Send one jacket.', 'internal_note' => 'Private assessment'])->assertHasNoActionErrors()->assertNotified();
        $this->assertSame('approved', $r->fresh()->status);
        $page->assertSee('Receive and classify returned units')->callAction(TestAction::make('receiveReturn'.$r->id)->schemaComponent(), ['lines' => [$r->lines()->sole()->id => ['resellable' => 1, 'damaged' => 0]], 'note' => 'Inspected'])->assertHasNoActionErrors()->assertNotified();
        $this->assertSame('received', $r->fresh()->status);
        $event = $order->events()->first();
        $this->get(route('orders.message', $event))->assertOk()->assertSee($order->number);
        $this->get(route('orders.show', $order->access_token))->assertDontSee('Private assessment')->assertDontSee('Inspected');
    }

    public function test_transfer_rejection_then_verified_payment_and_paid_cancellation(): void
    {
        $order = $this->order('bank');
        $order->update(['payment_status' => 'proof_submitted']);
        $care = app(OrderCare::class);
        $care->rejectTransfer($order->id, $this->staff->id, 'No funds located. Submit a clearer proof.', 'STAFF SECRET');
        $care->rejectTransfer($order->id, $this->staff->id, 'Duplicate');
        $this->assertSame('proof_rejected', $order->fresh()->payment_status);
        $this->get(route('orders.show', $order->access_token))->assertSee('Submit a clearer proof.')->assertDontSee('STAFF SECRET');
        app(Fulfillment::class)->verifyPayment($order->id, $this->staff->id, 'Receipt verified');
        $r = $care->request($order->id, 'cancellation', (string) Str::uuid(), 'Cancel paid order');
        $care->decide($order->id, $r->id, true, $this->staff->id, 'Cancelled. Refund will be recorded separately.');
        $this->assertDatabaseCount('order_refunds', 0);
        $care->refund($order->id, 22500, 'PKR', 'BANK-REFUND', $this->staff->id);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(0, $this->variant->fresh()->reserved);
    }

    public function test_preexisting_serialized_confirmation_job_is_compatible(): void
    {
        $order = $this->order();
        $job = (new \ReflectionClass(OrderNotification::class))->newInstanceWithoutConstructor();
        $job->orderId = $order->id;
        $mailer = \Mockery::mock();
        $mailer->shouldReceive('raw')->once();
        Mail::shouldReceive('mailer')->once()->andReturn($mailer);
        $job->handle();
        $job->handle();
        $this->assertNotNull($order->fresh()->notification_logged_at);
        $this->assertSame(1, $order->events()->where('event_key', 'confirmation')->count());
    }

    public function test_customer_multiline_form_retries_and_invalid_receipt_rolls_back_all_stock(): void
    {
        $order = $this->order();
        $second = $order->lines()->create(['product_variant_id' => $this->variant->id, 'product_name' => 'Snapshot second line', 'color' => 'Brown', 'size' => 'L', 'sku' => 'SNAPSHOT-L', 'quantity' => 1, 'unit_price_minor' => 10000, 'total_minor' => 10000, 'currency' => 'PKR']);
        // The second line is a saved fixture; reserve its unit before exercising fulfillment.
        DB::transaction(function () use ($order): void {
            app(Inventory::class)->change($this->variant->fresh(), 0, 1, 'Test second line', $order->id);
        });
        $this->deliver($order);
        $first = $order->lines()->first();
        $data = ['type' => 'return', 'submission_key' => (string) Str::uuid(), 'reason' => 'One of each', 'quantities' => [$first->id => 1, $second->id => 1]];
        $url = route('orders.request', $order->access_token);
        $this->post($url, $data)->assertRedirect(route('orders.show', $order->access_token));
        $this->post($url, $data)->assertRedirect();
        $this->post(route('orders.request', str_repeat('z', 64)), $data)->assertNotFound();
        $this->assertSame(1, $order->requests()->count());
        $request = $order->requests()->sole();
        $care = app(OrderCare::class);
        $care->decide($order->id, $request->id, true, $this->staff->id, 'Send these two units.');
        $lines = $request->lines()->get();
        $bad = [$lines[0]->id => ['resellable' => 1, 'damaged' => 0], $lines[1]->id => ['resellable' => 2, 'damaged' => 0]];
        $before = $this->variant->fresh()->on_hand;
        $this->invalid(fn () => $care->receive($order->id, $request->id, $bad, $this->staff->id));
        $this->assertSame($before, $this->variant->fresh()->on_hand);
        $this->assertDatabaseCount('return_receipts', 0);
        $bad[$lines[1]->id] = ['resellable' => 0, 'damaged' => 1];
        $care->receive($order->id, $request->id, $bad, $this->staff->id);
        $this->assertSame($before + 1, $this->variant->fresh()->on_hand);
        $this->assertDatabaseCount('return_receipts', 2);
    }

    public function test_additive_migration_preserves_old_receipts_and_backfills_verified_collection(): void
    {
        $order = $this->order();
        $this->deliver($order);
        app(Fulfillment::class)->verifyPayment($order->id, $this->staff->id, 'Old verified receipt');
        $lines = $order->lines()->get()->toArray();
        $total = $order->total_minor;
        $migration = require database_path('migrations/2026_11_20_082542_add_order_care_tables.php');
        $migration->down();
        $migration->up();
        $this->assertSame($total, $order->fresh()->total_minor);
        $this->assertSame($total, $order->fresh()->collected_minor);
        $this->assertNotNull($order->fresh()->delivered_at);
        $this->assertSame($lines, $order->lines()->get()->toArray());
        $this->assertSame(8, $this->variant->fresh()->on_hand);
    }
}
