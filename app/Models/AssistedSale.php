<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistedSale extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['email', 'phone', 'internal_note'];

    protected function casts(): array
    {
        return ['items' => 'array', 'snapshot' => 'array', 'subtotal_minor' => 'integer', 'version' => 'integer', 'expires_at' => 'datetime'];
    }

    public function links(): HasMany
    {
        return $this->hasMany(AssistedSaleLink::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getDisplayStatusAttribute(): string
    {
        return $this->status === 'shared' && $this->expires_at?->isPast() ? 'expired' : $this->status;
    }
}
