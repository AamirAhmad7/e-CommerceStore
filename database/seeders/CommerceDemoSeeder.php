<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CommerceSettings;
use App\Services\Inventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CommerceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        if (config("database.connections.$connection.database") !== 'leather_store_demo') {
            throw new \RuntimeException('Demo seeder only runs on leather_store_demo.');
        }
        User::firstOrCreate(['email' => 'demo@example.test'], ['name' => 'Demo owner', 'password' => Hash::make('Demo-only-2026!')]);
        $category = Category::firstOrCreate(['slug' => 'demo-jackets'], ['name' => 'Demo jackets', 'is_active' => true]);
        $product = Product::firstOrCreate(['slug' => 'demo-biker-jacket'], ['name' => 'Demo Biker Jacket', 'category_id' => $category->id, 'is_active' => true, 'description' => 'Isolated demo product. No real stock, payment or delivery promise.', 'pkr_price_minor' => 1290000, 'usd_price_minor' => 4900]);
        foreach (['Black' => ['S', 'M'], 'Brown' => ['M', 'L']] as $name => $sizes) {
            $color = $product->colors()->firstOrCreate(['name' => $name], ['swatch_hex' => $name === 'Black' ? '#111111' : '#664433']);
            foreach ($sizes as $size) {
                $variant = $color->variants()->firstOrCreate(['size' => $size], ['sku' => 'DEMO-'.strtoupper(substr($name, 0, 3)).'-'.$size, 'is_active' => true]);
                if ($variant->on_hand === null) {
                    app(Inventory::class)->adjust($variant->id, 5, 'Demo opening fixture', null);
                }
            }
        }
        CommerceSettings::save(['shipping_minor' => 25000, 'tax_basis_points' => 0, 'cod_enabled' => true, 'bank_enabled' => true, 'bank_instructions' => 'DEMO ONLY. Do not send money. Upload a test image or PDF to exercise the review flow.', 'expiry_hours' => 1]);
    }
}
