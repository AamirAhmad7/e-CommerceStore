<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public const OPEN = ['ordered', 'partially_received'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'expected_at' => 'date', 'issued_at' => 'datetime', 'freight_minor' => 'integer'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class);
    }

    public function getOutstandingAttribute(): int
    {
        return (int) $this->lines()->sum(DB::raw('quantity - accepted - damaged_short - short_closed'));
    }
}
