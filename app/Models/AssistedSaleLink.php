<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistedSaleLink extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'items' => 'array', 'snapshot' => 'array', 'subtotal_minor' => 'integer', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(AssistedSale::class, 'assisted_sale_id');
    }

    public function state(): string
    {
        if ($this->revoked_at) {
            return 'revoked';
        }
        if ($this->sale->order_id) {
            return 'converted';
        }

        return $this->expires_at->isPast() ? 'expired' : 'shared';
    }

    public function url(): string
    {
        return route('assisted.show', $this->token);
    }
}
