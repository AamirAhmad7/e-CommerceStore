<?php

namespace Database\Factories;

use App\Models\Courier;
use App\Models\CourierSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CourierSettlement>
 */
class CourierSettlementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'courier_id' => Courier::factory(), 'external_reference' => 'TEST-'.Str::ulid(), 'payload_hash' => hash('sha256', 'fixture'), 'payout_date' => now()->toDateString(), 'bank_reference' => 'Fictitious payout',
        ];
    }
}
