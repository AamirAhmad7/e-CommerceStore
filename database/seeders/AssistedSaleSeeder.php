<?php

namespace Database\Seeders;

use App\Models\AssistedSale;
use Illuminate\Database\Seeder;

class AssistedSaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \LogicException('Assisted sale fixtures are restricted to isolated in-memory tests.');
        }
        AssistedSale::factory()->create(['customer_label' => 'Isolated inquiry ? add SKUs before sharing']);
    }
}
