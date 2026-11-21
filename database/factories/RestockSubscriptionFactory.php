<?php

namespace Database\Factories;

use App\Models\RestockSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<RestockSubscription> */
class RestockSubscriptionFactory extends Factory
{
    public function definition(): array
    {
        $email = strtolower(fake()->unique()->safeEmail());
        $token = Str::random(64);

        return ['email' => $email, 'email_hash' => hash('sha256', $email), 'token' => $token, 'token_hash' => hash('sha256', $token), 'confirmation_expires_at' => now()->addDays(7)];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => ['confirmed_at' => now()]);
    }
}
