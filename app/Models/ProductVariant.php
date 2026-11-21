<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = [
        'reorder_point',
        'reorder_target',
        'size',
        'sku',
        'barcode',
        'weight_grams',
        'is_active',
        'usd_price_minor',
        'pkr_price_minor',
    ];

    protected function casts(): array
    {
        return [
            'reorder_point' => 'integer',
            'reorder_target' => 'integer',
            'weight_grams' => 'integer',
            'is_active' => 'boolean',
            'on_hand' => 'integer',
            'reserved' => 'integer',
        ];
    }

    public function productColor(): BelongsTo
    {
        return $this->belongsTo(ProductColor::class);
    }
}
