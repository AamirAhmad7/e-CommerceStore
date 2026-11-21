<?php

namespace Database\Factories;

use App\Models\AssistedSale;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssistedSale>
 */
class AssistedSaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'AS-'.Str::ulid(),
            'source' => 'whatsapp',
            'customer_label' => fake()->name(),
            'items' => [],
            'snapshot' => [],
            'subtotal_minor' => 0,
            'status' => 'draft',
            'version' => 1,
        ];
    }
}
