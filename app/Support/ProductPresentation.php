<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductColor;
use App\Services\Inventory;
use App\Services\Pricing;

final class ProductPresentation
{
    public static function variants(ProductColor $color, Product $product, string $currency, bool $preview = false): array
    {
        $variants = $color->variants->sort(fn ($a, $b) => (self::sizeKey($a->size) <=> self::sizeKey($b->size)) ?: ($a->id <=> $b->id))->map(function ($variant) use ($currency, $product, $preview): array {
            $price = Pricing::resolve($variant, $currency, $product);
            $checkoutPrice = Pricing::resolve($variant, 'PKR', $product);
            $available = Inventory::available($variant);
            $problem = $currency === 'PKR' && $checkoutPrice <= 0 ? 'PKR checkout price is not configured.' : ($price <= 0 ? $currency.' display price is not configured.' : ($variant->on_hand === null ? 'Stock is not configured.' : null));

            return ['id' => $variant->id, 'size' => $variant->size, 'label' => self::sizeLabel($variant->size), 'sku' => $variant->sku, 'price' => $price, 'price_label' => $price > 0 ? Pricing::format($price, $currency) : ($preview ? 'Display price is not configured.' : 'Price unavailable'), 'available' => $available, 'can_buy' => $currency === 'USD' ? ($variant->is_active && $price > 0 && $available > 0) : Inventory::purchasable($variant, $currency, $product), 'availability' => $problem ? ($preview ? $problem : ($price <= 0 || ($currency === 'PKR' && $checkoutPrice <= 0) ? 'Currently unavailable to order. Choose another size or ask the store about this item.' : 'Availability has not been confirmed. Choose another size or ask the store.')) : ($available > 0 ? ($available <= 5 ? 'Only '.$available.' left' : '') : 'Sold out'), 'disabled_label' => $variant->on_hand !== null && $available === 0 ? 'Sold out' : 'Currently unavailable', 'stock_configured' => $variant->on_hand !== null, 'can_notify' => ! $preview && $variant->is_active && $checkoutPrice > 0 && $price > 0 && $variant->on_hand !== null && $available === 0];
        })->values()->all();
        $labels = array_count_values(array_column($variants, 'label'));
        foreach ($variants as &$variant) {
            if ($labels[$variant['label']] > 1) {
                $variant['label'] .= ' ('.$variant['sku'].')';
            }
        }
        unset($variant);

        return $variants;
    }

    public static function preferredColor(Product $product, string $currency, ?string $name = null): ?ProductColor
    {
        $colors = $product->colors->filter(fn ($c) => $c->variants->where('is_active', true)->isNotEmpty());

        return ($name ? $colors->firstWhere('name', $name) : null) ?? $colors->first(fn ($c) => $c->variants->contains(fn ($v) => $v->is_active && Pricing::resolve($v, $currency, $product) > 0 && Inventory::available($v) > 0)) ?? $colors->first();
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
        ProductWorkspace::remember($product);
        $findings = [];
        $findings[] = ['kind' => 'Publication', 'url' => ! $product->is_active ? '#product-publication' : (! $product->category?->is_active && $product->category ? route('filament.admin.resources.categories.edit', $product->category) : null), 'text' => ! $product->is_active ? 'Draft: hidden from the public storefront.' : (! $product->category?->is_active ? 'Category is inactive: product is hidden.' : 'Product and category are active.')];
        foreach ($product->colors as $color) {
            $available = $color->variants->filter(fn ($variant) => $product->is_active && $product->category?->is_active && $variant->is_active && Pricing::resolve($variant, 'PKR', $product) > 0 && Inventory::available($variant) > 0)->count();
            $findings[] = ['kind' => 'Color summary', 'color' => $color->id, 'stock_variant' => ($color->variants->first(fn ($variant) => $variant->is_active && ($variant->on_hand === null || Inventory::available($variant) === 0)) ?? $color->variants->first())?->id, 'price_variant' => ($color->variants->first(fn ($variant) => $variant->is_active && Pricing::resolve($variant, 'PKR', $product) <= 0) ?? $color->variants->first())?->id, 'text' => $color->name.' · '.$color->variants->count().' sizes · '.$available.' available to order · '.$color->getMedia('gallery')->count().' photos'];
            $active = $color->variants->where('is_active', true);
            if ($active->isEmpty()) {
                $findings[] = ['kind' => 'Configuration', 'text' => $color->name.' has no active sizes. Add or activate a size; this color is hidden publicly.'];
            }
            if ($color->getMedia('gallery')->isEmpty()) {
                $findings[] = ['kind' => 'Optional improvement', 'text' => $color->name.' has no color photos. Upload or move a saved shared photo.'];
            }
            foreach ($active as $variant) {
                if (Pricing::resolve($variant, 'PKR', $product) <= 0) {
                    $findings[] = ['action' => 'managePrices', 'variant' => $variant->id, 'label' => 'Set PKR price', 'kind' => 'Configuration', 'text' => 'No checkout price for '.$color->name.' / '.$variant->size.' ('.$variant->sku.'). Set a PKR base price or override.'];
                }
                if ($variant->on_hand === null || Inventory::available($variant) === 0) {
                    $findings[] = ['action' => 'manageStock', 'variant' => $variant->id, 'label' => 'Enter stock', 'kind' => $variant->on_hand === null ? 'Configuration' : 'Normal stock state', 'text' => ($variant->on_hand === null ? 'Stock is not configured for ' : 'No available stock for ').$color->name.' / '.$variant->size.' ('.$variant->sku.').'];
                }
            }
            if ($active->isNotEmpty() && $active->every(fn ($v) => $v->on_hand !== null && Inventory::available($v) === 0)) {
                $findings[] = ['kind' => 'Normal stock state', 'text' => $color->name.' is sold out. It remains visible; no publication change is required.'];
            }
        }
        $unclassified = $product->getMedia('shared')->filter(fn ($m) => ! in_array($m->getCustomProperty('placement'), ['detail', 'size_guide'], true))->count();
        if ($unclassified) {
            $findings[] = ['kind' => 'Optional improvement', 'text' => $unclassified.' shared image'.($unclassified === 1 ? ' needs' : 's need').' classification. Use Photo use and alt text or Move shared photo.'];
        }

        return $findings;
    }
}
