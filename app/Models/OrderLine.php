<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['unit_paid_minor' => 'array', 'promotion_discount_minor' => 'integer', 'promotion_eligible' => 'boolean'];
    }
}
