<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestockSubscription extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['email', 'email_hash', 'token', 'token_hash'];

    protected function casts(): array
    {
        return ['email' => 'encrypted', 'token' => 'encrypted', 'confirmation_expires_at' => 'datetime', 'confirmation_sent_at' => 'datetime', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function scopeWaiting(Builder $query): void
    {
        $query->whereNotNull('confirmed_at')->whereNull('cancelled_at')->whereNull('completed_at');
    }
}
