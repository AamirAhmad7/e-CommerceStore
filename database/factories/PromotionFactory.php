<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Promotion> */
class PromotionFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'Test jacket offer', 'code' => strtoupper(fake()->unique()->bothify('TEST-####??')), 'automatic' => false, 'type' => 'percentage', 'percent_basis_points' => 1000, 'scope' => 'storewide', 'target_ids' => [], 'status' => 'active'];
    }
}
