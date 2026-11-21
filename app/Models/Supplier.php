<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sources(): HasMany
    {
        return $this->hasMany(SupplierSource::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function whatsappUrl(): ?string
    {
        return preg_match('/^[0-9]{7,15}$/', $this->whatsapp ?? '') ? 'https://wa.me/'.$this->whatsapp : null;
    }
}
