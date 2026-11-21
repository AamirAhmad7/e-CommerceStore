<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\RestockSubscription;
use App\Services\Inventory;
use App\Services\RestockAlerts;
use Illuminate\Database\Seeder;

class RestockSubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        if (config("database.connections.$connection.database") !== 'leather_store_demo' || config('mail.default') !== 'log' || config('store.media_disk') !== 'demo') {
            throw new \RuntimeException('Restock demo requires the isolated demo database, demo media and logged mail.');
        }
        $category = Category::firstOrCreate(['slug' => 'demo-restock'], ['name' => 'Demo restock jackets', 'is_active' => true]);
        $product = Product::firstOrCreate(['slug' => 'demo-restock-jacket'], ['name' => 'Demo Restock Jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 1000000, 'usd_price_minor' => 10000]);
        foreach (['Black' => 0, 'Brown' => 5] as $name => $quantity) {
            $color = $product->colors()->firstOrCreate(['name' => $name]);
            $variant = $color->variants()->firstOrCreate(['sku' => 'DEMO-RESTOCK-'.strtoupper($name).'-M'], ['size' => 'M', 'is_active' => true]);
            if ($variant->on_hand === null) {
                app(Inventory::class)->adjust($variant->id, $quantity, 'DEMO restock opening stock', null);
            }
            if ($name === 'Black') {
                app(RestockAlerts::class)->subscribe($variant->id, 'restock-demo@example.test');
                app(RestockAlerts::class)->subscribe($variant->id, 'waiting-demo@example.test');
                $waiting = RestockSubscription::where('product_variant_id', $variant->id)->where('email_hash', hash('sha256', 'waiting-demo@example.test'))->first();
                if ($waiting) {
                    app(RestockAlerts::class)->confirm($waiting->token);
                }
                $this->command?->info('Demo product: '.RestockAlerts::buyUrl($variant));
            }
        }
    }
}
