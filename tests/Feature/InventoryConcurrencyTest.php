<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('MySQL concurrency test; run with isolated MySQL environment.');
        }
        $this->assertSame('leather_store_variant_regression_test', config('database.connections.mysql.database'));
    }

    private function payload(): array
    {
        $category = Category::create(['name' => 'Race', 'slug' => 'race', 'is_active' => true]);
        $product = Product::create(['name' => 'Last unit', 'slug' => 'last-unit', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 10000]);
        $variant = $product->colors()->create(['name' => 'Black'])->variants()->create(['size' => 'M', 'sku' => 'RACE-M', 'is_active' => true]);
        app(Inventory::class)->adjust($variant->id, 1, 'Isolated concurrency fixture', null);
        CommerceSettings::save(['shipping_minor' => 0, 'tax_basis_points' => 0, 'cod_enabled' => true]);
        $items = [$variant->id => 1];
        $quote = app(Cart::class)->quote($items);

        return ['guest' => hash('sha256', 'buyer-one'), 'key' => (string) Str::uuid(), 'customer' => ['name' => 'Race Buyer', 'email' => 'race@example.test', 'phone' => '+923000000000', 'address' => 'Test only', 'city' => 'Lahore', 'region' => 'Punjab', 'country' => 'PK', 'payment_method' => 'cod'], 'review' => ['items' => $items, 'fingerprint' => Cart::fingerprint($quote, CommerceSettings::totals($quote['subtotal']))]];
    }

    private function race(array $first, array $second): array
    {
        $dir = storage_path('framework/testing/race-'.Str::uuid());
        mkdir($dir, 0777, true);
        $barrier = $dir.'/go';
        $workers = [];
        try {
            foreach ([$first, $second] as $i => $payload) {
                $file = $dir.'/payload-'.$i.'.json';
                file_put_contents($file, json_encode($payload, JSON_THROW_ON_ERROR));
                $pipes = [];
                $process = proc_open([PHP_BINARY, base_path('tests/Support/checkout-race.php'), $file, $barrier, (string) $i], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $workers[] = [$process, $pipes];
            }
            $deadline = microtime(true) + 12;
            while (! is_file($barrier.'.ready-0') || ! is_file($barrier.'.ready-1')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Workers did not reach the start barrier.');
                } usleep(20000);
                clearstatcache();
            }
            file_put_contents($barrier, 'go');
            $results = [];
            foreach ($workers as [$process,$pipes]) {
                $output = stream_get_contents($pipes[1]);
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $error);
                $results[] = trim($output);
            }

            return $results;
        } finally {
            foreach ($workers as [$process]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
            foreach (glob($dir.'/*') as $path) {
                unlink($path);
            } rmdir($dir);
        }
    }

    public function test_two_buyers_racing_for_last_unit_only_one_succeeds(): void
    {
        $first = $this->payload();
        $second = $first;
        $second['guest'] = hash('sha256', 'buyer-two');
        $second['key'] = (string) Str::uuid();
        $results = $this->race($first, $second);
        $this->assertCount(1, array_filter($results, fn ($r) => str_starts_with($r, 'OK:')));
        $this->assertContains('REJECTED', $results);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, ProductVariant::sole()->reserved);
        $this->assertSame(1, ProductVariant::sole()->on_hand);
        $this->assertSame(2, StockMovement::count());
    }

    public function test_concurrent_identical_checkout_key_returns_same_order_once(): void
    {
        $payload = $this->payload();
        $results = $this->race($payload, $payload);
        $this->assertSame($results[0], $results[1]);
        $this->assertStringStartsWith('OK:', $results[0]);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, ProductVariant::sole()->reserved);
        $this->assertSame(2, StockMovement::count());
    }

    private function careFixture(): array
    {
        Queue::fake();
        $payload = $this->payload();
        $order = app(Checkout::class)->create($payload['guest'], $payload['key'], $payload['customer'], $payload['review']);
        $actor = User::factory()->create()->id;

        return [$order, $actor];
    }

    public function test_two_staff_approve_one_cancellation_once(): void
    {
        [$order, $actor] = $this->careFixture();
        $request = app(OrderCare::class)->request($order->id, 'cancellation', (string) Str::uuid(), 'Cancel');
        $payload = ['operation' => 'decide', 'order' => $order->id, 'request' => $request->id, 'actor' => $actor];
        $results = $this->race($payload, array_replace($payload, ['actor' => User::factory()->create()->id]));
        $this->assertSame(['OK:'.$order->id, 'OK:'.$order->id], $results);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, ProductVariant::sole()->reserved);
        $this->assertSame(3, StockMovement::count());
        $this->assertSame(1, $order->events()->where('event_key', 'decision-'.$request->id)->count());
    }

    public function test_shipment_racing_cancellation_has_one_valid_winner(): void
    {
        [$order, $actor] = $this->careFixture();
        foreach (['confirmed', 'processing'] as $status) {
            app(Fulfillment::class)->transition($order->id, $status, $actor);
        }
        $request = app(OrderCare::class)->request($order->id, 'cancellation', (string) Str::uuid(), 'Cancel');
        $results = $this->race(['operation' => 'decide', 'order' => $order->id, 'request' => $request->id, 'actor' => $actor], ['operation' => 'ship', 'order' => $order->id, 'actor' => $actor]);
        $this->assertContains('REJECTED', $results);
        $this->assertCount(1, array_filter($results, fn ($r) => str_starts_with($r, 'OK:')));
        $this->assertContains($order->fresh()->status, ['cancelled', 'shipped']);
        $this->assertSame(0, ProductVariant::sole()->reserved);
        $this->assertSame(3, StockMovement::count());
    }

    public function test_concurrent_return_receipts_and_refunds_cannot_double_stock_or_exceed_collection(): void
    {
        [$order, $actor] = $this->careFixture();
        CommerceSettings::save(array_replace(CommerceSettings::get(), ['returns_enabled' => true]));
        foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $status) {
            app(Fulfillment::class)->transition($order->id, $status, $actor);
        }
        app(Fulfillment::class)->verifyPayment($order->id, $actor, 'COD receipt');
        $care = app(OrderCare::class);
        $request = $care->request($order->id, 'return', (string) Str::uuid(), 'Return', [$order->lines()->sole()->id => 1]);
        $care->decide($order->id, $request->id, true, $actor, 'Return approved');
        $payload = ['operation' => 'receive', 'order' => $order->id, 'request' => $request->id, 'actor' => $actor, 'lines' => [$request->lines()->sole()->id => ['resellable' => 1, 'damaged' => 0]]];
        $this->assertSame(['OK:'.$order->id, 'OK:'.$order->id], $this->race($payload, $payload));
        $this->assertSame(1, ProductVariant::sole()->on_hand);
        $this->assertDatabaseCount('return_receipts', 1);
        $this->assertSame(4, StockMovement::count());
        $first = ['operation' => 'refund', 'order' => $order->id, 'actor' => $actor, 'amount' => 7000, 'reference' => 'RACE-REFUND-A'];
        $second = array_replace($first, ['reference' => 'RACE-REFUND-B']);
        $results = $this->race($first, $second);
        $this->assertContains('REJECTED', $results);
        $this->assertCount(1, array_filter($results, fn ($r) => str_starts_with($r, 'OK:')));
        $this->assertDatabaseCount('order_refunds', 1);
    }
}
