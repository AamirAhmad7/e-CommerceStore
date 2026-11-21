<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shipment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'expected_cod_minor' => 'integer', 'collected_minor' => 'integer', 'packed_at' => 'datetime', 'dispatched_at' => 'datetime', 'received_at' => 'datetime', 'collection_confirmed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(DispatchManifest::class, 'dispatch_manifest_id');
    }

    public function settlementLine(): HasOne
    {
        return $this->hasOne(CourierSettlementLine::class);
    }

    public function trackingUrl(): ?string
    {
        return $this->tracking_number && filled($this->tracking_template) && Courier::validTemplate($this->tracking_template) ? str_replace('{tracking}', rawurlencode($this->tracking_number), $this->tracking_template) : null;
    }
}
