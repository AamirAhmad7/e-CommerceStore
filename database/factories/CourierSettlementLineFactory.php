<?php

namespace Database\Factories;

use App\Models\CourierSettlement;
use App\Models\CourierSettlementLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourierSettlementLine>
 */
class CourierSettlementLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'courier_settlement_id' => CourierSettlement::factory(), 'tracking_number' => '000TEST'.fake()->unique()->numerify('####'), 'collection_minor' => 1850000, 'gross_minor' => 1850000, 'fee_minor' => 25000, 'net_minor' => 1825000,
        ];
    }
}
