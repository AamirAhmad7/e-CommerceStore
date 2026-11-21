<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class CollectionItem extends Model
{
    protected $guarded = ['id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if ($item->featured_color_id && ! ProductColor::whereKey($item->featured_color_id)->where('product_id', $item->product_id)->exists()) {
                throw ValidationException::withMessages(['featured_color_id' => 'Choose a color belonging to this product.']);
            }
        });
    }
}
