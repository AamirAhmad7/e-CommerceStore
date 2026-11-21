<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PurchaseOrder> */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return ['reference' => 'PO-'.Str::ulid(), 'supplier_id' => Supplier::factory(), 'supplier_name' => 'Factory supplier', 'status' => 'draft', 'version' => 1];
    }
}
