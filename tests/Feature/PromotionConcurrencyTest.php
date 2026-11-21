<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Services\Cart;
use App\Services\CommerceSettings;
use App\Services\Inventory;
use App\Services\Promotions;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PromotionConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires the isolated MySQL test database.');
        }$this->assertSame('leather_store_variant_regression_test', config('database.connections.mysql.database'));
    }

    private function payload(): array
    {
        $category = Category::create(['name' => 'Promotion race', 'slug' => 'promotion-race', 'is_active' => true]);
        $product = Product::create(['name' => 'Race jacket', 'slug' => 'race-jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 100000]);
        $v = $product->colors()->create(['name' => 'Black'])->variants()->create(['size' => 'M', 'sku' => 'PROMO-RACE-M', 'is_active' => true]);
        app(Inventory::class)->adjust($v->id, 10, 'Isolated promotion race', null);
        CommerceSettings::save(['shipping_minor' => 10000, 'tax_basis_points' => 0, 'cod_enabled' => true]);
        Promotion::factory()->create(['code' => 'LASTUSE', 'use_limit' => 1]);
        $items = [$v->id => 1];
        $quote = app(Cart::class)->quote($items);
        $r = app(Promotions::class)->resolve($quote, CommerceSettings::get(), 'LASTUSE');

        return ['guest' => hash('sha256', 'buyer-one'), 'key' => (string) Str::uuid(), 'customer' => ['name' => 'Race buyer', 'email' => 'race@example.test', 'phone' => '03000000000', 'address' => 'Fictitious', 'city' => 'Sialkot', 'region' => 'Punjab', 'country' => 'PK', 'payment_method' => 'cod'], 'review' => ['items' => $items, 'promotion_code' => 'LASTUSE', 'fingerprint' => Cart::fingerprint($quote, $r['totals'])]];
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

    public function test_two_checkouts_cannot_claim_the_final_use(): void
    {
        $first = $this->payload();
        $second = $first;
        $second['guest'] = hash('sha256', 'buyer-two');
        $second['key'] = (string) Str::uuid();
        $results = $this->race($first, $second);
        $this->assertCount(1, array_filter($results, fn ($r) => str_starts_with($r, 'OK:')));
        $this->assertContains('REJECTED', $results);
        $this->assertSame(1, Promotion::sole()->uses);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, ProductVariant::sole()->reserved);
        $this->assertSame(1, DB::table('promotion_redemptions')->count());
    }

    public function test_identical_retry_commits_one_order_and_one_use(): void
    {
        $payload = $this->payload();
        $results = $this->race($payload, $payload);
        $this->assertSame($results[0], $results[1]);
        $this->assertStringStartsWith('OK:', $results[0]);
        $this->assertSame(1, Promotion::sole()->uses);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, DB::table('promotion_redemptions')->count());
        $this->assertSame(1,ProductVariant::sole()->reserved);
    }
}
