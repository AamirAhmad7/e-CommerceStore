<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;

final class ProductWorkspace
{
    public static function forget(): void
    {
        request()->attributes->remove('product_workspace_variants');
    }

    public static function remember(Product $product): Product
    {
        $variants = request()->attributes->get('product_workspace_variants', []);
        foreach ($product->colors as $color) {
            foreach ($color->variants as $variant) {
                $variants[$variant->id] = $variant;
            }
        }
        request()->attributes->set('product_workspace_variants', $variants);

        return $product;
    }

    public static function variant(ProductVariant $record): ProductVariant
    {
        $variants = request()->attributes->get('product_workspace_variants', []);
        if (! isset($variants[$record->id])) {
            $siblings = ProductVariant::whereHas('productColor', fn (Builder $query) => $query->whereIn('product_id', ProductColor::whereKey($record->product_color_id)->select('product_id')))->get();
            foreach ($siblings as $variant) {
                $variants[$variant->id] = $variant;
            }
            request()->attributes->set('product_workspace_variants', $variants);
        }

        return $variants[$record->id] ?? $record;
    }

    public static function attention(string $issue): Builder
    {
        $query = ProductVariant::query()->where('product_variants.is_active', true)
            ->whereHas('productColor.product', fn (Builder $products) => $products->visible());
        if (in_array($issue, ['price', 'missing_price'], true)) {
            $query->when($issue === 'price', fn (Builder $stock) => $stock->whereColumn('on_hand', '>', 'reserved'))->whereRaw('(COALESCE(product_variants.pkr_price_minor, (SELECT products.pkr_price_minor FROM products JOIN product_colors ON product_colors.product_id = products.id WHERE product_colors.id = product_variants.product_color_id), 0)) <= 0');
        } else {
            $query->where(fn (Builder $stock) => $stock->whereNull('on_hand')->orWhereColumn('on_hand', '<=', 'reserved'));
        }

        return $query;
    }
}
