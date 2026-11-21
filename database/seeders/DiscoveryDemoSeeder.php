<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Services\Inventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DiscoveryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        if (config("database.connections.$connection.database") !== 'leather_store_demo' || config('store.media_disk') !== 'demo') {
            throw new \RuntimeException('Discovery fixtures require the isolated demo database and media disk.');
        }
        DB::transaction(function (): void {
            $category = Category::firstOrCreate(['slug' => 'demo-discovery'], ['name' => 'Demo discovery jackets', 'is_active' => true]);
            $product = Product::firstOrCreate(['slug' => 'demo-discovery-jacket'], ['name' => 'Demo Discovery Jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 1000000, 'usd_price_minor' => 10000, 'description' => 'Isolated discovery example: Black M sold out, Black L and Brown M available.']);
            foreach (['Black' => ['M' => 0, 'L' => 5], 'Brown' => ['M' => 5]] as $name => $sizes) {
                $color = $product->colors()->firstOrCreate(['name' => $name]);
                foreach ($sizes as $size => $quantity) {
                    $variant = $color->variants()->firstOrCreate(['sku' => 'DEMO-DISCOVERY-'.strtoupper($name).'-'.$size], ['size' => $size, 'is_active' => true, 'pkr_price_minor' => $name === 'Brown' ? 1200000 : null, 'usd_price_minor' => $name === 'Brown' ? 15000 : null]);
                    if ($variant->on_hand === null) {
                        app(Inventory::class)->adjust($variant->id, $quantity, 'DEMO discovery opening stock', null);
                    }
                }
            }
            $collection = Collection::firstOrCreate(['slug' => 'demo-discovery'], ['title' => 'Demo discovery', 'is_published' => true]);
            $collection->items()->firstOrCreate(['product_id' => $product->id], ['sort_order' => 0]);
        });
    }
}
