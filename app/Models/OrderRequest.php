<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderRequest extends Model
{
    protected $guarded = ['id'];

    public function lines(): HasMany
    {
        return $this->hasMany(OrderRequestLine::class);
    }
}
