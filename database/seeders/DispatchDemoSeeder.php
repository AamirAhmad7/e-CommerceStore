<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Courier;
use App\Models\CourierSettlement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CourierSettlements;
use App\Services\Dispatching;
use App\Services\Fulfillment;
use App\Services\Inventory;
use App\Services\OrderMessages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DispatchDemoSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        if (config("database.connections.$connection.database") !== 'leather_store_demo' || config('mail.default') !== 'log') {
            throw new \RuntimeException('Dispatch examples require leather_store_demo and MAIL_MAILER=log. Normal databases are never allowed.');
        }
        $owner = User::where('email', 'demo@example.test')->firstOrFail();
        auth()->login($owner);
        $category = Category::firstOrCreate(['slug' => 'demo-dispatch'], ['name' => 'Demo dispatch fixtures', 'is_active' => true]);
        $product = Product::firstOrCreate(['slug' => 'demo-dispatch-jacket'], ['name' => 'Demo Dispatch Jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 925000, 'description' => 'Fictitious dispatch training item. Do not ship or pay.']);
        $color = $product->colors()->firstOrCreate(['name' => 'Black']);
        $variant = $color->variants()->firstOrCreate(['sku' => 'DEMO-DISPATCH-BLK-M'], ['size' => 'M', 'is_active' => true]);
        if ($variant->on_hand === null) {
            app(Inventory::class)->adjust($variant->id, 20, 'Isolated dispatch demo opening stock', $owner->id);
        }
        $courier = Courier::firstOrCreate(['name' => 'Demo Manual Courier'], ['service_name' => 'Fictitious surface service', 'tracking_template' => 'https://example.com/track/{tracking}', 'is_active' => true]);
        foreach (['DEMO-DISPATCH-PREPAID' => ['bank', null], 'DEMO-DISPATCH-COD' => ['cod', '00090002'], 'DEMO-DISPATCH-RTO' => ['cod', '00090003'], 'DEMO-DISPATCH-SHORT' => ['cod', '00090004']] as $reference => [$method, $tracking]) {
            if (Order::where('number', $reference)->exists()) {
                continue;
            }
            DB::transaction(function () use ($reference, $method, $tracking, $variant, $courier, $owner): void {
                $order = $this->fixtureOrder($reference, $method, $variant);
                if ($method === 'bank') {
                    app(Fulfillment::class)->verifyPayment($order->id, $owner->id, 'DEMO verified prepaid - no real payment');
                }
                app(Dispatching::class)->pack($order->id, Dispatching::snapshot($order), $order->lines->pluck('quantity', 'id')->all());
                if (! $tracking) {
                    return;
                }
                app(Dispatching::class)->handover([['order_id' => $order->id, 'snapshot' => Dispatching::snapshot($order), 'courier_id' => $courier->id, 'tracking_number' => $tracking]], (string) Str::uuid());
                if ($reference === 'DEMO-DISPATCH-RTO') {
                    app(Dispatching::class)->event($order->id, Dispatching::snapshot($order), 'issue', 'customer_unavailable', 'DEMO internal routing note', 'This fictitious delivery attempt was unsuccessful.');
                    app(Dispatching::class)->event($order->id, Dispatching::snapshot($order), 'returning', 'refusal');
                } else {
                    app(Dispatching::class)->event($order->id, Dispatching::snapshot($order), 'delivered', 'Fictitious delivery evidence');
                    app(Fulfillment::class)->verifyPayment($order->id, $owner->id, 'DEMO verified COD collection - no real money');
                }
            });
        }
        if (! CourierSettlement::where('courier_id', $courier->id)->where('external_reference', 'DEMO-COURIER-PAYOUT-250')->exists()) {
            $data = ['courier_id' => $courier->id, 'external_reference' => 'DEMO-COURIER-PAYOUT-250', 'payout_date' => now()->toDateString(), 'bank_reference' => 'FICTITIOUS payout; no transfer', 'review_note' => 'Training discrepancy: courier still owes PKR 250', 'accept_discrepancies' => true, 'rows' => [['tracking_number' => '00090004', 'gross_cod' => '18500.00', 'fee_deduction' => '250.00', 'net_remitted' => '18000.00']]];
            $plan = app(CourierSettlements::class)->preview($data);
            app(CourierSettlements::class)->post($data, $plan['token']);
        }
        $this->command?->info('Open Orders > Dispatch > Packed: DEMO-DISPATCH-PREPAID. Settlement: DEMO-COURIER-PAYOUT-250. No real money or outreach.');
    }

    /** Fixed financial snapshots keep examples repeatable without changing demo checkout settings. */
    private function fixtureOrder(string $reference, string $method, ProductVariant $variant): Order
    {
        $locked = ProductVariant::lockForUpdate()->findOrFail($variant->id);
        $order = Order::create(['number' => $reference, 'guest_hash' => hash('sha256', $reference), 'access_token' => Str::random(64), 'customer' => ['name' => 'Fictitious Dispatch Recipient', 'email' => 'dispatch-recipient@example.test', 'phone' => '03000000000', 'address' => 'DEMO ONLY - no physical delivery', 'city' => 'Sialkot', 'region' => 'Punjab', 'country' => 'PK', 'payment_method' => $method], 'status' => 'pending', 'payment_method' => $method, 'payment_status' => 'unpaid', 'payment_instructions' => 'DEMO ONLY - do not pay', 'subtotal_minor' => 1850000, 'discount_minor' => 0, 'tax_minor' => 0, 'shipping_minor' => 0, 'total_minor' => 1850000, 'tax_basis_points' => 0, 'shipping_name' => 'Fictitious demo delivery', 'currency' => 'PKR']);
        $order->lines()->create(['product_variant_id' => $variant->id, 'product_name' => 'Demo Dispatch Jacket', 'color' => 'Black', 'size' => 'M', 'sku' => $variant->sku, 'quantity' => 2, 'unit_price_minor' => 925000, 'total_minor' => 1850000, 'currency' => 'PKR']);
        app(Inventory::class)->change($locked, 0, 2, 'Demo order reservation', $order->id, auth()->id());
        $order->histories()->create(['actor_id' => auth()->id(), 'status' => 'pending', 'payment_status' => 'unpaid', 'note' => 'Isolated dispatch fixture; no real purchase.']);
        OrderMessages::confirmation($order->id);

        return $order;
    }
}
