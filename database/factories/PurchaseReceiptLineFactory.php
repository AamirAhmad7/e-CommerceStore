<?php

namespace Database\Factories;

use App\Models\PurchaseOrderLine;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseReceiptLine> */
class PurchaseReceiptLineFactory extends Factory
{
    public function definition(): array
    {
        $line = PurchaseOrderLine::factory()->create();

        return ['purchase_order_line_id' => $line->id, 'purchase_receipt_id' => PurchaseReceipt::factory()->state(['purchase_order_id' => $line->purchase_order_id]), 'accepted' => 1, 'damaged_replace' => 0, 'damaged_short' => 0, 'short_closed' => 0, 'unit_cost_minor' => 350000];
    }
}
