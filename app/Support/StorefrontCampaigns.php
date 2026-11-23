<?php

namespace App\Support;

use App\Models\Collection;
use App\Models\Product;
use App\Services\Pricing;
use Illuminate\Support\Str;

final class StorefrontCampaigns
{
    public static function url(string $name, array $parameters = []): string
    {
        return rtrim(config('app.url'), '/').route($name, $parameters, false);
    }

    public static function cardRelations(): array
    {
        return ['product.colors.variants' => fn ($q) => $q->where('is_active', true), 'product.colors.media' => fn ($q) => $q->where('collection_name', 'gallery')->orderBy('order_column')->orderBy('id')->limit(1)];
    }

    public static function card(Product $product, string $currency, ?int $featuredColor = null): array
    {
        $color = $product->colors->first(fn ($c) => $c->id === $featuredColor && $c->variants->where('is_active', true)->isNotEmpty()) ?? ProductPresentation::preferredColor($product, $currency);
        $media = $color?->getFirstMedia('gallery');
        if ($media) {
            $media->setRelation('model', $color);
        }

        return ['quick_color' => $featuredColor ?? $color?->id, 'product' => $product, 'color' => $color, 'photo' => $media ? ProductPhoto::data($media) : null, 'price' => str_replace('Price not configured', 'Currently unavailable', Pricing::range($product, $currency)), 'url' => route('products.show', [$product->slug, 'currency' => $currency, 'color' => $color?->id])];
    }

    public static function cover(Collection $collection, ?array $fallback = null): ?array
    {
        $media = $collection->getFirstMedia('cover');
        if (! $media) {
            return $fallback;
        }
        $media->setRelation('model', $collection);
        $photo = ProductPhoto::data($media);
        $photo['alt'] = $collection->cover_alt ?? '';

        return $photo;
    }

    public static function description(Collection $collection): string
    {
        return Str::limit(Str::squish(strip_tags($collection->introduction ?: 'Explore '.$collection->title.' at '.StoreSettings::brandName().'.')), 155, '');
    }
}
