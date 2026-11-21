<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'accepted' => 'integer', 'damaged_replace' => 'integer', 'damaged_short' => 'integer', 'short_closed' => 'integer', 'unit_cost_minor' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getOutstandingAttribute(): int
    {
        return max(0, $this->quantity - $this->accepted - $this->damaged_short - $this->short_closed);
    }
}
