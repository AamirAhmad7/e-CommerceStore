<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\CommerceSettings;
use App\Services\Inventory;
use App\Services\Promotions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PromotionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        if (config("database.connections.$connection.database") !== 'leather_store_demo' || config('mail.default') !== 'log') {
            throw new \RuntimeException('Promotion demo requires leather_store_demo and logged mail.');
        }
        $owner = User::where('email', 'demo@example.test')->firstOrFail();
        auth()->login($owner);
        DB::transaction(function () use ($owner): void {
            $category = Category::firstOrCreate(['slug' => 'demo-promotions'], ['name' => 'Demo promotions', 'is_active' => true]);
            $jacket = Product::firstOrCreate(['slug' => 'demo-winter-campaign-jacket'], ['name' => 'Demo Winter Jacket', 'category_id' => $category->id, 'description' => 'Fictitious jacket for promotion practice.', 'is_active' => true, 'pkr_price_minor' => 1000000, 'usd_price_minor' => 12000]);
            $variants = [];
            foreach (['Black', 'Brown'] as $name) {
                $color = $jacket->colors()->firstOrCreate(['name' => $name]);
                foreach (['S', 'M', 'L'] as $size) {
                    $v = $color->variants()->firstOrCreate(['sku' => 'DEMO-OFFER-'.strtoupper($name).'-'.$size], ['size' => $size, 'is_active' => true]);
                    if ($v->on_hand === null) {
                        app(Inventory::class)->adjust($v->id, 20, 'Isolated promotion demo opening stock', $owner->id);
                    }$variants[$name.'-'.$size] = $v;
                }
            }
            $belt = Product::firstOrCreate(['slug' => 'demo-campaign-belt'], ['name' => 'Demo Belt (excluded from Winter10)', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 200000, 'usd_price_minor' => 2500]);
            $other = $belt->colors()->firstOrCreate(['name' => 'Tan'])->variants()->firstOrCreate(['sku' => 'DEMO-OFFER-BELT'], ['size' => 'One size', 'is_active' => true]);
            if ($other->on_hand === null) {
                app(Inventory::class)->adjust($other->id, 20, 'Isolated promotion demo opening stock', $owner->id);
            }
            $collection = Collection::firstOrCreate(['slug' => 'demo-winter-jackets'], ['title' => 'Demo Winter Jackets', 'introduction' => 'Try WINTER10: 10% off eligible jackets, minimum PKR 15,000, capped at PKR 2,000.', 'is_published' => true]);
            $collection->items()->firstOrCreate(['product_id' => $jacket->id], ['featured_color_id' => $variants['Black-M']->product_color_id, 'sort_order' => 1]);
            $example = [['variant_id' => $variants['Black-M']->id, 'quantity' => 3], ['variant_id' => $other->id, 'quantity' => 1]];
            $winter = Promotion::firstOrCreate(['code' => 'WINTER10'], ['name' => 'Winter Jackets', 'automatic' => false, 'type' => 'percentage', 'percent_basis_points' => 1000, 'cap_minor' => 200000, 'minimum_minor' => 1500000, 'scope' => 'collections', 'target_ids' => [$collection->id], 'example_items' => $example, 'landing_collection_id' => $collection->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(2), 'use_limit' => 100]);
            Promotion::firstOrCreate(['code' => 'JACKET500'], ['name' => 'PKR 500 jacket saving', 'automatic' => false, 'type' => 'fixed', 'amount_minor' => 50000, 'minimum_minor' => 500000, 'scope' => 'products', 'target_ids' => [$jacket->id], 'example_items' => $example, 'landing_collection_id' => $collection->id, 'status' => 'active']);
            Promotion::firstOrCreate(['code' => 'SHIPFREE'], ['name' => 'Free standard shipping', 'automatic' => false, 'type' => 'shipping', 'scope' => 'storewide', 'example_items' => [['variant_id' => $variants['Black-M']->id, 'quantity' => 1]], 'landing_collection_id' => $collection->id, 'status' => 'active']);
            Promotion::firstOrCreate(['code' => 'WINTEROLD'], ['name' => 'Expired winter example', 'automatic' => false, 'type' => 'percentage', 'percent_basis_points' => 1000, 'scope' => 'storewide', 'status' => 'active', 'starts_at' => now()->subDays(10), 'ends_at' => now()->subDay()]);
            $key = '0d700000-0000-4000-8000-000000000001';
            if (! DB::table('checkout_attempts')->where('key', $key)->whereNotNull('order_id')->exists() && ! $winter->availability()) {
                $items = [$variants['Black-M']->id => 3, $other->id => 1];
                $quote = app(Cart::class)->quote($items);
                $r = app(Promotions::class)->resolve($quote, CommerceSettings::get(), 'WINTER10');
                $order = app(Checkout::class)->create(hash('sha256', 'isolated-promotion-demo'), $key, ['name' => 'Fictitious Campaign Buyer', 'email' => 'promotion-buyer@example.test', 'phone' => '03000000000', 'address' => 'DEMO ONLY - do not ship', 'city' => 'Sialkot', 'region' => 'Punjab', 'country' => 'PK', 'payment_method' => 'cod'], ['items' => $items, 'promotion_code' => 'WINTER10', 'fingerprint' => Cart::fingerprint($quote, $r['totals'])]);
                $this->command?->info('Example order using WINTER10: '.$order->number);
            }
        });
        $this->command?->info('Demo: /collections/demo-winter-jackets?offer=WINTER10. Codes: WINTER10, JACKET500, SHIPFREE, expired WINTEROLD.');
    }
}
