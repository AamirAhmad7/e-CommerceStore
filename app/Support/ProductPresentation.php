<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductColor;
use App\Services\Inventory;
use App\Services\Pricing;

final class ProductPresentation
{
    public static function preferredColor(Product $product, string $currency, ?string $name = null): ?ProductColor
    {
        $colors = $product->colors->filter(fn ($c) => $c->variants->where('is_active', true)->isNotEmpty());

        return ($name ? $colors->firstWhere('name', $name) : null) ?? $colors->first(fn ($c) => $c->variants->contains(fn ($v) => $v->is_active && Pricing::resolve($v, $currency, $product) > 0 && Pricing::resolve($v, 'PKR', $product) > 0 && Inventory::available($v) > 0)) ?? $colors->first();
    }

    public static function sizeLabel(string $size): string
    {
        $label = strtoupper(trim($size));

        return match ($label) {
            '2XL' => 'XXL','3XL' => 'XXXL','4XL' => 'XXXXL','2XS' => 'XXS','3XS' => 'XXXS',default => preg_match('/^(?:X{0,4}[SL]|M)$/', $label) ? $label : trim($size)
        };
    }

    public static function sizeKey(string $size): array
    {
        $label = self::sizeLabel($size);
        $sizes = ['XXXS', 'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'XXXXL'];
        $rank = array_search($label, $sizes, true);

        return $rank !== false ? [0, $rank, ''] : (is_numeric($label) ? [1, (float) $label, ''] : [2, 0, strtolower($label)]);
    }

    public static function price(array $variants, bool $preview): string
    {
        $prices = collect($variants)->pluck('price')->filter(fn ($p) => $p > 0);
        if ($prices->isEmpty()) {
            return $preview ? 'Display price is not configured.' : 'Currently unavailable';
        }
        $first = collect($variants)->first(fn ($v) => $v['price'] === $prices->min());

        return ($prices->unique()->count() > 1 || $prices->count() !== count($variants) ? 'From ' : '').$first['price_label'];
    }

    public static function readiness(Product $product): array
    {
        $product->loadMissing('category', 'media', 'colors.media', 'colors.variants');
        $findings = [];
        $findings[] = ['kind' => 'Publication', 'text' => ! $product->is_active ? 'Draft: hidden from the public storefront.' : (! $product->category?->is_active ? 'Category is inactive: product is hidden.' : 'Product and category are active.')];
        foreach ($product->colors as $color) {
            $missingPrices = $color->variants->filter(fn ($variant) => Pricing::resolve($variant, 'PKR', $product) <= 0)->count();
            $unconfiguredStock = $color->variants->whereNull('on_hand')->count();
            $outOfStock = $color->variants->filter(fn ($variant) => $variant->on_hand !== null && Inventory::available($variant) === 0)->count();
            $findings[] = ['kind' => 'Color summary', 'text' => $color->name.' · '.$color->variants->count().' sizes · '.$missingPrices.' missing checkout prices · '.$outOfStock.' out of stock · '.$unconfiguredStock.' stock not configured'];
            $active = $color->variants->where('is_active', true);
            if ($active->isEmpty()) {
                $findings[] = ['kind' => 'Configuration', 'text' => $color->name.' has no active sizes. Add or activate a size; this color is hidden publicly.'];
            }
            if ($color->getMedia('gallery')->isEmpty()) {
                $findings[] = ['kind' => 'Configuration', 'text' => $color->name.' has no color photos. Upload or move a saved shared photo.'];
            }
            foreach ($active as $variant) {
                if (Pricing::resolve($variant, 'PKR', $product) <= 0) {
                    $findings[] = ['action' => 'managePrices', 'variant' => $variant->id, 'label' => 'Set PKR price', 'kind' => 'Configuration', 'text' => 'No checkout price for '.$color->name.' / '.$variant->size.' ('.$variant->sku.'). Set a PKR base price or override.'];
                }
                if ($variant->on_hand === null) {
                    $findings[] = ['action' => 'manageStock', 'variant' => $variant->id, 'label' => 'Enter stock', 'kind' => 'Configuration', 'text' => 'Stock is not configured for '.$color->name.' / '.$variant->size.' ('.$variant->sku.').'];
                }
            }
            if ($active->isNotEmpty() && $active->every(fn ($v) => $v->on_hand !== null && Inventory::available($v) === 0)) {
                $findings[] = ['kind' => 'Normal stock state', 'text' => $color->name.' is sold out. It remains visible; no publication change is required.'];
            }
        }
        $unclassified = $product->getMedia('shared')->filter(fn ($m) => ! in_array($m->getCustomProperty('placement'), ['detail', 'size_guide'], true))->count();
        if ($unclassified) {
            $findings[] = ['kind' => 'Configuration', 'text' => $unclassified.' shared image'.($unclassified === 1 ? ' needs' : 's need').' classification. Use Photo use and alt text or Move shared photo.'];
        }

        return $findings;
    }
}
