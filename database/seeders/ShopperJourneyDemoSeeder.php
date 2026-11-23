<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Services\CommerceSettings;
use App\Services\Inventory;
use Illuminate\Database\Seeder;

class ShopperJourneyDemoSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        if (config("database.connections.$connection.database") !== 'leather_store_demo' || config('store.media_disk') !== 'demo') {
            throw new \RuntimeException('Shopper fixtures require the isolated demo database and media disk.');
        }
        $category = Category::firstOrCreate(['slug' => 'demo-journey'], ['name' => 'Demo shopper jackets', 'is_active' => true]);
        foreach (['demo-journey-biker' => 'Demo Journey Biker', 'demo-journey-windbreaker' => 'Demo Journey Windbreaker'] as $slug => $name) {
            $product = Product::firstOrCreate(['slug' => $slug], ['name' => $name, 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 1200000, 'usd_price_minor' => 15000, 'description' => 'Isolated shopper demo. Fictional stock and prices; do not send payment.']);
            foreach (['Black', 'Brown'] as $name) {
                $color = $product->colors()->firstOrCreate(['name' => $name]);
                foreach (['M' => 5, 'L' => 4, 'XL' => 0] as $size => $quantity) {
                    $variant = $color->variants()->firstOrCreate(['sku' => strtoupper($slug.'-'.$name.'-'.$size)], ['size' => $size, 'is_active' => true]);
                    if ($variant->wasRecentlyCreated) {
                        app(Inventory::class)->adjust($variant->id, $quantity, 'Isolated shopper demo opening stock', null);
                    }
                }
            }
            $this->command?->info(route('products.show', ['product' => $slug, 'market' => 'international']));
        }
        $settings = CommerceSettings::get();
        $settings['international_shipping_mode'] = 'separate';
        $settings['international_shipping_rates'] = [['destination' => 'United Kingdom', 'amount' => '25.00', 'basis' => 'order']];
        CommerceSettings::save($settings);
        $this->command?->info('Demo only: United Kingdom shipping estimate USD 25 per order. Other destinations require a quote.');
    }
}
