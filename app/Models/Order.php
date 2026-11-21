<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['access_token', 'guest_hash', 'proof_path'];

    protected function casts(): array
    {
        return ['promotion_snapshot' => 'array', 'customer' => 'array', 'expires_at' => 'datetime', 'delivered_at' => 'datetime', 'collected_minor' => 'integer'];
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function getDispatchStatusAttribute(): string
    {
        return in_array($this->status, ['cancelled', 'expired', 'delivered', 'returned'], true) ? $this->status : ($this->shipment?->stage ?? (in_array($this->status, ['pending', 'confirmed', 'processing'], true) ? 'ready' : 'with_courier'));
    }

    public function assistedSale(): HasOne
    {
        return $this->hasOne(AssistedSale::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderHistory::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(OrderRequest::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }
}
