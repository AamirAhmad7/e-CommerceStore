<?php

namespace Database\Factories;

use App\Models\AssistedSale;
use App\Models\AssistedSaleLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssistedSaleLink>
 */
class AssistedSaleLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::random(64);

        return [
            'assisted_sale_id' => AssistedSale::factory(),
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'items' => [],
            'snapshot' => [],
            'subtotal_minor' => 0,
            'expires_at' => now()->addHours(72),
        ];
    }
}
