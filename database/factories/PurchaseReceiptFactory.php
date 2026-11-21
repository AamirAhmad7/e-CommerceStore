<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PurchaseReceipt> */
class PurchaseReceiptFactory extends Factory
{
    public function definition(): array
    {
        return ['reference' => 'RCV-'.Str::ulid(), 'purchase_order_id' => PurchaseOrder::factory()->state(['status' => 'ordered', 'issued_at' => now()]), 'submission_key' => (string) Str::uuid(), 'payload_hash' => hash('sha256', fake()->uuid()), 'kind' => 'delivery'];
    }
}
