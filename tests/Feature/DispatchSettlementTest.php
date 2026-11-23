<?php

namespace Tests\Feature;

use App\Filament\Pages\CourierSettlements as SettlementsPage;
use App\Filament\Pages\Dispatch;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Category;
use App\Models\Courier;
use App\Models\CourierSettlement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\CommerceSettings;
use App\Services\CourierSettlements;
use App\Services\Dispatching;
use App\Services\Fulfillment;
use App\Services\Inventory;
use App\Services\OrderCare;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DispatchSettlementTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;

    private Courier $courier;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $this->assertContains(config("database.connections.$connection.database"), [':memory:', 'leather_store_variant_regression_test']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $category = Category::create(['name' => 'Dispatch fixtures', 'slug' => 'dispatch-fixtures', 'is_active' => true]);
        $product = Product::create(['name' => 'Dispatch jacket', 'slug' => 'dispatch-jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 925000]);
        $this->variant = $product->colors()->create(['name' => 'Black'])->variants()->create(['size' => 'M', 'sku' => 'DISP-BLK-M', 'is_active' => true]);
        app(Inventory::class)->adjust($this->variant->id, 20, 'Isolated dispatch stock', auth()->id());
        $this->courier = Courier::create(['name' => 'Fictitious Courier', 'tracking_template' => 'https://courier.example/track?number={tracking}', 'is_active' => true]);
        CommerceSettings::save(['shipping_minor' => 0, 'tax_basis_points' => 0, 'cod_enabled' => true, 'bank_enabled' => true, 'bank_instructions' => 'No real transfer', 'returns_enabled' => true, 'return_days' => 14]);
    }

    private function order(string $method = 'cod'): Order
    {
        $items = [$this->variant->id => 2];
        $quote = app(Cart::class)->quote($items);

        return app(Checkout::class)->create(hash('sha256', Str::random(20)), (string) Str::uuid(), ['name' => 'Fictitious recipient', 'email' => 'recipient@example.test', 'phone' => '03000000000', 'address' => 'TEST ONLY street', 'city' => 'Sialkot', 'region' => 'Punjab', 'country' => 'PK', 'payment_method' => $method], ['items' => $items, 'fingerprint' => Cart::fingerprint($quote, CommerceSettings::totals($quote['subtotal']))]);
    }

    private function pack(Order $order): Shipment
    {
        return app(Dispatching::class)->pack($order->id, Dispatching::snapshot($order), $order->lines->pluck('quantity', 'id')->all());
    }

    private function handoverRow(Order $order, string $tracking): array
    {
        return ['order_id' => $order->id, 'snapshot' => Dispatching::snapshot($order), 'courier_id' => $this->courier->id, 'tracking_number' => $tracking];
    }

    private function deliverAndCollect(string $tracking): Order
    {
        $order = $this->order();
        $this->pack($order);
        app(Dispatching::class)->handover([$this->handoverRow($order, $tracking)], (string) Str::uuid());
        app(Dispatching::class)->event($order->id, Dispatching::snapshot($order), 'delivered', 'Delivery confirmation');
        $this->assertSame(0, $order->fresh()->collected_minor);
        app(Fulfillment::class)->verifyPayment($order->id, auth()->id(), 'Verified customer collection '.$tracking);

        return $order->fresh();
    }

    private function invalid(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected validation failure.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_duplicate_dispatch_and_stale_packing_do_not_repeat_stock_or_notifications(): void
    {
        $order = $this->order();
        $prepaid = $this->order('bank');
        $this->invalid(fn () => $this->pack($prepaid));
        app(Fulfillment::class)->verifyPayment($prepaid->id, auth()->id(), 'Verified prepaid receipt');
        $picking = $this->get(route('dispatch.picking', ['orders' => [$order->id, $prepaid->id]]))->assertOk()->assertSee('DISP-BLK-M');
        $this->assertStringContainsString('<td>4</td>', $picking->getContent());
        $this->assertSame(20, $this->variant->fresh()->on_hand);
        $snapshot = Dispatching::snapshot($order);
        $quantities = $order->lines->pluck('quantity', 'id')->all();
        app(Dispatching::class)->pack($order->id, $snapshot, $quantities);
        app(Dispatching::class)->pack($order->id, $snapshot, $quantities);
        $this->pack($prepaid);
        $this->assertSame(20, $this->variant->fresh()->on_hand);
        $rows = [$this->handoverRow($order, '000123/A B'), $this->handoverRow($prepaid, '000124')];
        $badRows = $rows;
        $badRows[1]['courier_id'] = 999999;
        $this->invalid(fn () => app(Dispatching::class)->handover($badRows, (string) Str::uuid()));
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame(20, $this->variant->fresh()->on_hand);
        $bulkPage = Livewire::test(Dispatch::class)->set('stage', 'packed')->selectTableRecords([$order->id, $prepaid->id])->mountAction(TestAction::make('handover')->table()->bulk())->assertSet('mountedActions.0.name', 'handover');
        $this->assertCount(2, $bulkPage->get('mountedActions.0.data.rows'));
        $key = (string) Str::uuid();
        $manifest = app(Dispatching::class)->handover($rows, $key);
        $same = app(Dispatching::class)->handover($rows, $key);
        $this->assertSame($manifest->id, $same->id);
        $this->invalid(fn () => app(Dispatching::class)->handover($rows, (string) Str::uuid()));
        $this->assertSame(16, $this->variant->fresh()->on_hand);
        $this->assertSame(0, $this->variant->fresh()->reserved);
        $this->assertSame(2, StockMovement::where('reason', 'Order shipped')->count());
        $this->assertSame(1, $order->events()->where('event_key', 'status-shipped')->count());
        $this->assertSame(0, $prepaid->fresh()->shipment->expected_cod_minor);
        $this->assertSame(1850000, $order->fresh()->shipment->expected_cod_minor);
        $this->get(route('dispatch.manifest', $manifest))->assertOk()->assertSee('000123/A B')->assertSee('PKR 18,500');
        $this->courier->update(['is_active' => false, 'tracking_template' => 'https://changed.example/{tracking}']);
        $this->assertSame('https://courier.example/track?number=000123%2FA%20B', $order->fresh()->shipment->trackingUrl());
        $this->get(route('orders.show', $order->access_token))->assertOk()->assertSee('Track parcel')->assertDontSee('Net remitted');
        $cancelled = $this->order();
        $stale = Dispatching::snapshot($cancelled);
        app(Fulfillment::class)->transition($cancelled->id, 'cancelled', auth()->id());
        $this->invalid(fn () => app(Dispatching::class)->pack($cancelled->id, $stale, $cancelled->lines->pluck('quantity', 'id')->all()));
        Livewire::test(Dispatch::class)->set('stage', 'with_courier')->assertCanSeeTableRecords([$order, $prepaid]);
        Livewire::test(ViewOrder::class, ['record' => $order->id])->assertSee('Dispatch and courier ledger');
    }

    public function test_courier_return_restores_only_resellable_units_once_and_never_refunds_unpaid_cod(): void
    {
        $order = $this->order();
        $this->pack($order);
        app(Dispatching::class)->handover([$this->handoverRow($order, '000200')], (string) Str::uuid());
        app(Dispatching::class)->event($order->id, Dispatching::snapshot($order), 'issue', 'customer_unavailable', 'PRIVATE ROUTE NOTE', 'Please contact the store to arrange delivery.');
        $this->assertSame(18, $this->variant->fresh()->on_hand);
        $this->assertSame('shipped', $order->fresh()->status);
        $this->get(route('orders.show', $order->access_token))->assertDontSee('PRIVATE ROUTE NOTE')->assertSee('Please contact the store');
        app(Dispatching::class)->event($order->id, Dispatching::snapshot($order), 'with_courier', 'Reattempt');
        app(Dispatching::class)->event($order->id, Dispatching::snapshot($order), 'returning', 'Refusal');
        $this->invalid(fn () => app(Fulfillment::class)->transition($order->id, 'delivered', auth()->id()));
        $snapshot = Dispatching::snapshot($order);
        $line = $order->lines()->sole();
        $this->invalid(fn () => app(Dispatching::class)->receive($order->id, $snapshot, [$line->id => ['resellable' => 2, 'damaged' => 1]]));
        app(Dispatching::class)->receive($order->id, $snapshot, [$line->id => ['resellable' => 1, 'damaged' => 1]], 'Physical inspection');
        app(Dispatching::class)->receive($order->id, $snapshot, [$line->id => ['resellable' => 1, 'damaged' => 1]], 'Retry');
        $this->assertSame(19, $this->variant->fresh()->on_hand);
        $this->assertSame(19, Inventory::available($this->variant->fresh()));
        $this->assertSame('returned', $order->fresh()->status);
        $this->assertSame(1, DB::table('courier_return_receipts')->count());
        $this->assertSame(1, DB::table('courier_return_receipts')->value('damaged'));
        $this->assertSame(0, DB::table('order_refunds')->count());
        $this->invalid(fn () => app(OrderCare::class)->refund($order->id, 100, 'PKR', 'Cannot refund unpaid COD', auth()->id()));
        $this->invalid(fn () => app(OrderCare::class)->request($order->id, 'return', (string) Str::uuid(), 'Wrong return workflow', [$line->id => 1]));
    }

    public function test_settlement_preview_idempotency_mismatch_and_audited_correction(): void
    {
        $one = $this->deliverAndCollect('000301');
        $two = $this->deliverAndCollect('000302');
        $service = app(CourierSettlements::class);
        $data = ['courier_id' => $this->courier->id, 'external_reference' => 'FAKE-PAYOUT-1', 'payout_date' => now()->format('Y-m-d'), 'bank_reference' => 'No real bank transfer', 'review_note' => 'Second parcel is short PKR 250', 'rows' => [
            ['tracking_number' => '000301', 'gross_cod' => '18500.00', 'fee_deduction' => '250.00', 'net_remitted' => '18250.00'],
            ['tracking_number' => '000302', 'gross_cod' => '18500.00', 'fee_deduction' => '250.00', 'net_remitted' => '18000.00'],
        ]];
        $preview = $service->preview($data);
        $this->assertFalse($preview['blocked']);
        $this->assertSame(0, $preview['rows'][0]['difference']);
        $this->assertSame(25000, $preview['rows'][1]['difference']);
        $this->invalid(fn () => $service->post($data, $preview['token']));
        $data['accept_discrepancies'] = true;
        $statement = $service->post($data, $preview['token']);
        $same = $service->post($data, $preview['token']);
        $reuploaded = $service->preview($data);
        $this->assertSame($statement->id, $service->post($data, $reuploaded['token'])->id);
        $this->assertSame($statement->id, $same->id);
        $this->assertSame(1, CourierSettlement::count());
        $this->assertSame(25000, CourierSettlements::totals()['outstanding']);
        $this->assertSame(25000, CourierSettlements::totals()['unresolved']);
        $this->assertSame(1850000, (int) $two->fresh()->total_minor);
        $this->assertSame(1850000, $two->fresh()->collected_minor);
        $this->assertSame(16, $this->variant->fresh()->on_hand);
        $line = $statement->lines()->where('tracking_number', '000302')->sole();
        $adjustment = ['reference' => 'FAKE-SUPPLEMENT-1', 'reason' => 'Courier paid remaining shortfall', 'gross_delta' => '0', 'fee_delta' => '0', 'net_delta' => '250.00'];
        $service->adjust($line->id, $adjustment);
        $service->adjust($line->id, $adjustment);
        $this->assertSame(1, DB::table('courier_settlement_adjustments')->count());
        $this->assertSame(1800000, (int) $line->fresh()->net_minor);
        $this->assertSame(0, CourierSettlements::totals()['unresolved']);
        $this->assertSame(0, CourierSettlements::totals()['outstanding']);
        $service->adjust($line->id, ['reference' => 'OVERPAY', 'reason' => 'Extra ten rupees', 'gross_delta' => '0', 'fee_delta' => '0', 'net_delta' => '10']);
        $this->assertSame(1000, CourierSettlements::totals()['unresolved']);
        $this->assertSame(0, CourierSettlements::totals()['outstanding']);
        $service->adjust($line->id, ['reference' => 'OVERPAY-CORRECTION', 'reason' => 'Correct extra ten rupees', 'gross_delta' => '0', 'fee_delta' => '0', 'net_delta' => '-10']);
        $this->assertSame(0, CourierSettlements::totals()['unresolved']);
        $bad = $data;
        $bad['external_reference'] = 'FAKE-PAYOUT-2';
        $bad['rows'][] = $bad['rows'][0];
        $bad['rows'][] = ['tracking_number' => 'UNKNOWN', 'gross_cod' => '1', 'fee_deduction' => '0', 'net_remitted' => '1'];
        $badPreview = $service->preview($bad);
        $this->assertTrue($badPreview['blocked']);
        $this->assertContains('Duplicate tracking row', array_column($badPreview['rows'], 'problem'));
        $this->assertContains('Unknown tracking number', array_column($badPreview['rows'], 'problem'));
        $this->invalid(fn () => $service->post($bad, $badPreview['token']));
        $csv = implode(',', CourierSettlements::HEADER)."\nFictitious Courier,CSV-TEST,000099,18500.00,250.00,18250.00\n";
        $this->assertSame('000099', $service->csv($csv, $this->courier->id, 'CSV-TEST')[0]['tracking_number']);
        $this->get(route('settlements.template'))->assertOk();
        Livewire::test(SettlementsPage::class)->assertCanSeeTableRecords($statement->lines);
        $this->assertSame(0, DB::table('order_refunds')->count());
    }
}
