<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SupplierSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseOrderLine> */
class PurchaseOrderLineFactory extends Factory
{
    public function definition(): array
    {
        $source = SupplierSource::factory()->create();
        $variant = $source->variant;

        return ['purchase_order_id' => PurchaseOrder::factory()->state(['supplier_id' => $source->supplier_id, 'supplier_name' => $source->supplier->name]), 'product_variant_id' => $variant->id, 'product_name' => $variant->productColor->product->name, 'color' => $variant->productColor->name, 'size' => $variant->size, 'sku' => $variant->sku, 'supplier_sku' => $source->supplier_sku, 'quantity' => 5, 'unit_cost_minor' => 350000];
    }
}
