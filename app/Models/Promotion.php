<?php

namespace App\Models;

use App\Support\StorefrontCampaigns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'uses'];

    protected function casts(): array
    {
        return ['automatic' => 'boolean', 'target_ids' => 'array', 'example_items' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'uses' => 'integer', 'use_limit' => 'integer', 'version' => 'integer', 'amount_minor' => 'integer', 'cap_minor' => 'integer', 'minimum_minor' => 'integer', 'minimum_units' => 'integer', 'percent_basis_points' => 'integer'];
    }

    public function availability(): ?string
    {
        if ($this->status !== 'active') {
            return 'This offer is unavailable.';
        }if ($this->starts_at?->isFuture()) {
            return 'This offer has not started yet.';
        }if ($this->ends_at && now()->gte($this->ends_at)) {
            return 'This offer has expired.';
        }if ($this->use_limit !== null && $this->uses >= $this->use_limit) {
            return 'This offer has reached its use limit.';
        }

return null;
    }

    public function publicUrl(): ?string
    {
        if ($this->availability()) {
            return null;
        } $collection = $this->landing_collection_id ? Collection::where('is_published', true)->find($this->landing_collection_id) : null;
        if ($this->landing_collection_id && ! $collection) {
            return null;
        }

return StorefrontCampaigns::url($collection ? 'collections.show' : 'shop', ($collection ? ['collection' => $collection->slug] : []) + ($this->automatic ? [] : ['offer' => $this->code]));
    }
}
