<?php

namespace Database\Factories;

use App\Models\Courier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Courier>
 */
class CourierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Test courier '.fake()->unique()->numerify('#######'), 'is_active' => true, 'tracking_template' => 'https://courier.example/track/{tracking}',
        ];
    }
}
