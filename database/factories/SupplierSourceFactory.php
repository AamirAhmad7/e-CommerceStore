<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SupplierSource> */
class SupplierSourceFactory extends Factory
{
    public function definition(): array
    {
        return ['supplier_id' => Supplier::factory(), 'product_variant_id' => function (): int {
            $key = Str::ulid();
            $category = Category::create(['name' => 'Factory category', 'slug' => 'factory-'.$key, 'is_active' => true]);
            $product = Product::create(['name' => 'Factory jacket', 'slug' => 'jacket-'.$key, 'category_id' => $category->id, 'is_active' => true]);

            return $product->colors()->create(['name' => 'Black'])->variants()->create(['sku' => 'SKU-'.$key, 'size' => 'M', 'is_active' => true])->id;
        }, 'supplier_sku' => fake()->unique()->bothify('SUP-#####'), 'is_preferred' => false, 'pack_size' => 1, 'minimum_quantity' => 1, 'quoted_cost_minor' => 350000, 'quoted_at' => now()];
    }
}
