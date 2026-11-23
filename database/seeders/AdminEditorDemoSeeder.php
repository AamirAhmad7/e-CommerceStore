<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Services\Inventory;
use Illuminate\Database\Seeder;

class AdminEditorDemoSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        if (config("database.connections.$connection.database") !== 'leather_store_demo' || config('store.media_disk') !== 'demo') {
            throw new \RuntimeException('Admin editor fixtures require the isolated demo database and media disk.');
        }
        $category = Category::firstOrCreate(['slug' => 'demo-editor'], ['name' => 'Demo editor jackets', 'is_active' => true]);
        $product = Product::firstOrCreate(['slug' => 'demo-classic-biker-jacket'], ['name' => 'Classic Biker Jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 1290000, 'usd_price_minor' => 14900, 'description' => 'Isolated admin editor example. No real stock or delivery promise.']);
        $color = $product->colors()->firstOrCreate(['name' => 'Black']);
        foreach (['S' => null, 'M' => 5, 'L' => 0, 'XL' => 3, 'XXL' => 2] as $size => $quantity) {
            $variant = $color->variants()->firstOrCreate(['sku' => 'DEMO-EDITOR-BLK-'.$size], ['size' => $size, 'is_active' => true, 'pkr_price_minor' => $size === 'XXL' ? 0 : null]);
            if ($variant->wasRecentlyCreated && $quantity !== null) {
                app(Inventory::class)->adjust($variant->id, $quantity, 'Isolated admin editor fixture', null);
            }
        }
        $this->command?->info('Demo editor: '.route('filament.admin.resources.products.edit', $product));
    }
}
