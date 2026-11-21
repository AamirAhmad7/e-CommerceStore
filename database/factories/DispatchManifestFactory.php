<?php

namespace Database\Factories;

use App\Models\DispatchManifest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DispatchManifest>
 */
class DispatchManifestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_key' => (string) Str::uuid(), 'reference' => 'MAN-'.Str::ulid(), 'payload_hash' => hash('sha256', 'fixture'),
        ];
    }
}
