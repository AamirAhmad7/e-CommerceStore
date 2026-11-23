<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name', 'seo_title', 'seo_description',
        'slug',
        'description',
        'is_active',
        'usd_price_minor',
        'pkr_price_minor',
        'delivery_charge_minor',
        'size_guide_id', 'size_guide_mode', 'size_guide_override', 'material', 'lining', 'fit', 'care', 'closure',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'size_guide_override' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function sizeGuide(): BelongsTo
    {
        return $this->belongsTo(SizeGuide::class);
    }

    public function applicableSizeGuide(): ?array
    {
        $guide = $this->size_guide_mode === 'override' ? $this->size_guide_override : $this->sizeGuide?->toArray();

        return filled($guide['measurements'] ?? []) ? $guide : null;
    }

    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true)->whereHas('category', fn ($category) => $category->where('is_active', true));
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('shared')
            ->useDisk(config('store.media_disk'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('storefront')
            ->performOnCollections('shared')
            ->fit(Fit::Max, 1600, 1600)
            ->format('webp')
            ->quality(80)
            ->withResponsiveImages()
            ->queued();
    }

    public function colors(): HasMany
    {
        return $this->hasMany(ProductColor::class)->orderBy('sort_order')->orderBy('id');
    }

    public function variants(): HasManyThrough
    {
        return $this->hasManyThrough(ProductVariant::class, ProductColor::class);
    }
}
