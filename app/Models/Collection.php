<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Collection extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CollectionItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function eligibleItems(): HasMany
    {
        return $this->items()->whereHas('product', fn ($q) => $q->visible()->whereHas('colors.variants', fn ($v) => $v->where('is_active', true)));
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->useDisk(config('store.media_disk'))->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('storefront')->performOnCollections('cover')->fit(Fit::Max, 1600, 1600)->format('webp')->quality(80)->withResponsiveImages()->queued();
    }
}
