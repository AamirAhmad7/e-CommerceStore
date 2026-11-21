<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Services\Inventory;
use App\Services\Pricing;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CatalogDiscovery
{
    /** @return array{products: LengthAwarePaginator, cards: \Illuminate\Support\Collection, categories: \Illuminate\Support\Collection, colors: \Illuminate\Support\Collection, sizes: \Illuminate\Support\Collection, filters: array, chips: array, clearUrl: string, currency: string, sort: string, noindex: bool} */
    public function browse(Request $request, ?Category $category = null, ?Collection $collection = null): array
    {
        $noindex = $request->hasAny(['q', 'category', 'color', 'size', 'in_stock', 'min', 'max', 'sort', 'currency']);
        foreach (['category', 'color', 'size'] as $group) {
            if (is_string($request->query($group))) {
                $request->merge([$group => array_values(array_filter([$request->query($group)]))]);
            }
        }
        $filters = $request->validate(['q' => 'nullable|string|max:160', 'category' => 'nullable|array|max:30', 'category.*' => 'string|max:180', 'color' => 'nullable|array|max:30', 'color.*' => 'string|max:80', 'size' => 'nullable|array|max:30', 'size.*' => 'string|max:30', 'in_stock' => 'nullable|boolean', 'min' => 'nullable|regex:/^\d{1,8}(\.\d{1,2})?$/', 'max' => 'nullable|regex:/^\d{1,8}(\.\d{1,2})?$/', 'currency' => 'nullable|in:PKR,USD', 'price_currency' => 'nullable|in:PKR,USD', 'sort' => 'nullable|in:relevance,curated,newest,price_asc,price_desc']);
        $currency = Pricing::currency($filters['currency'] ?? $request->session()->get('currency'));
        $filters['q'] = Str::squish($filters['q'] ?? '');
        $filters['currency'] = $currency;
        if (($filters['price_currency'] ?? $currency) !== $currency) {
            unset($filters['min'], $filters['max']);
        }
        unset($filters['price_currency']);
        $filters = array_filter($filters, fn ($value) => filled($value) && $value !== '0');
        $request->query->replace($filters + $request->only('page'));
        $request->session()->put('currency', $currency);
        if (! $request->routeIs('collections.preview')) {
            $request->session()->put('continue_shopping', $request->getRequestUri());
        }
        $term = mb_strtolower($filters['q'] ?? '');
        $field = strtolower($currency).'_price_minor';
        $base = Product::visible();
        if ($category) {
            $base->where('category_id', $category->id);
        }
        if ($collection) {
            $base->whereIn('products.id', $collection->items()->select('product_id'));
        }
        $optionsBase = clone $base;
        if (! empty($filters['category'])) {
            $base->whereHas('category', fn ($q) => $q->whereIn('slug', $filters['category']));
        }
        $variants = DB::table('product_variants as v')->join('product_colors as c', 'c.id', '=', 'v.product_color_id')->join('products as p', 'p.id', '=', 'c.product_id')->where('v.is_active', true);
        if (! empty($filters['color'])) {
            $variants->whereIn('c.name', $filters['color']);
        }
        if (! empty($filters['size'])) {
            $variants->whereIn('v.size', $filters['size']);
        }
        $price = "COALESCE(v.$field,p.$field)";
        if (! empty($filters['in_stock'])) {
            $variants->whereRaw('('.Inventory::availableSql('v').') > 0')->whereRaw('COALESCE(v.pkr_price_minor,p.pkr_price_minor) > 0')->whereRaw("$price > 0");
        }
        foreach (['min' => '>=', 'max' => '<='] as $key => $operator) {
            if (isset($filters[$key])) {
                $variants->whereRaw("$price > 0")->whereRaw("$price $operator ?", [Pricing::parse($filters[$key], $currency)]);
            }
        }
        if ($term !== '') {
            $variants->whereRaw('(LOWER(v.sku) = ? OR NOT EXISTS (SELECT 1 FROM product_variants ev JOIN product_colors ec ON ec.id=ev.product_color_id WHERE ec.product_id=p.id AND ev.is_active=1 AND LOWER(ev.sku)=?))', [$term, $term]);
        }
        $base->whereIn('products.id', (clone $variants)->select('c.product_id'));
        if ($term !== '') {
            $base->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(products.name) = ?', [$term])->orWhereHas('variants', fn ($v) => $v->where('product_variants.is_active', true)->whereRaw('LOWER(sku) = ?', [$term]));
                $q->orWhere(function ($words) use ($term): void {
                    foreach (explode(' ', $term) as $word) {
                        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word).'%';
                        $words->where(function ($part) use ($like): void {
                            $part->whereRaw("LOWER(products.name) LIKE ? ESCAPE '!'", [$like])->orWhereRaw("LOWER(products.description) LIKE ? ESCAPE '!'", [$like])->orWhereHas('category', fn ($c) => $c->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$like]))->orWhereHas('colors', fn ($c) => $c->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$like])->whereHas('variants', fn ($v) => $v->where('is_active', true)));
                        });
                    }
                });
            });
        }
        $base->select(['products.id', 'products.category_id', 'products.name', 'products.slug', 'products.pkr_price_minor', 'products.usd_price_minor'])->selectSub((clone $variants)->whereColumn('c.product_id', 'products.id')->selectRaw("MIN(CASE WHEN $price > 0 THEN $price END)"), 'sort_price');
        $sort = $filters['sort'] ?? ($term !== '' ? 'relevance' : ($collection ? 'curated' : 'newest'));
        if ($sort === 'relevance' && $term !== '') {
            $base->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM product_variants sv JOIN product_colors sc ON sc.id=sv.product_color_id WHERE sc.product_id=products.id AND sv.is_active=1 AND LOWER(sv.sku)=?) THEN 0 WHEN LOWER(products.name)=? THEN 1 WHEN LOWER(products.name) LIKE ? THEN 2 ELSE 3 END', [$term, $term, '%'.$term.'%']);
        } elseif (in_array($sort, ['price_asc', 'price_desc'], true)) {
            $base->orderByRaw('sort_price IS NULL')->orderBy('sort_price', $sort === 'price_desc' ? 'desc' : 'asc');
        } elseif ($sort === 'curated' && $collection) {
            $base->orderBy(DB::table('collection_items')->select('sort_order')->where('collection_id', $collection->id)->whereColumn('product_id', 'products.id')->limit(1))->orderBy(DB::table('collection_items')->select('id')->where('collection_id', $collection->id)->whereColumn('product_id', 'products.id')->limit(1));
        }
        if ($collection) {
            $base->selectSub(DB::table('collection_items')->select('featured_color_id')->where('collection_id', $collection->id)->whereColumn('product_id', 'products.id')->limit(1), 'featured_color_id');
        }
        $products = $base->orderByDesc('products.id')->paginate(12)->withQueryString();
        $ids = $products->modelKeys();
        $matchingIds = (clone $variants)->whereIn('c.product_id', $ids)->select('v.id');
        $products->load(['colors' => fn ($q) => $q->whereHas('variants', fn ($v) => $v->whereIn('id', $matchingIds)), 'colors.variants' => fn ($q) => $q->whereIn('id', $matchingIds)->select(['id', 'product_color_id', 'size', 'sku', 'is_active', 'on_hand', 'reserved', 'pkr_price_minor', 'usd_price_minor']), 'colors.media' => fn ($q) => $q->where('collection_name', 'gallery')->orderBy('order_column')->orderBy('id')->limit(1)]);
        $cards = $products->getCollection()->map(function (Product $product) use ($currency, $term, $filters): array {
            $exactColor = $product->colors->first(fn ($c) => $c->variants->contains(fn ($v) => mb_strtolower($v->sku) === $term));
            $color = $exactColor ?? $product->colors->first(fn ($c) => $term !== '' && mb_strtolower($c->name) === $term) ?? $product->colors->firstWhere('id', $product->featured_color_id) ?? ProductPresentation::preferredColor($product, $currency);
            $card = StorefrontCampaigns::card($product, $currency, $color?->id);
            $variant = $exactColor?->variants->first(fn ($v) => mb_strtolower($v->sku) === $term);
            if (! $variant && count($filters['size'] ?? []) === 1) {
                $variant = $color?->variants->firstWhere('size', $filters['size'][0]);
            }
            if ($variant) {
                $card['url'] = route('products.show', [$product->slug, 'currency' => $currency, 'color' => $color->id, 'size' => $variant->size]);
                $card['detail'] = $variant->size.' - '.(Inventory::purchasable($variant, $currency, $product) ? 'In stock' : 'Currently unavailable');
                if ($exactColor) {
                    $card['price'] = Pricing::format(Pricing::resolve($variant, $currency, $product), $currency);
                    $card['detail'] = $variant->sku.' - '.$card['detail'];
                }
            }

            return $card;
        });
        $categories = Category::where('is_active', true)->whereIn('id', (clone $optionsBase)->select('category_id'))->orderBy('name')->get(['id', 'name', 'slug']);
        $optionVariants = DB::table('product_variants as v')->join('product_colors as c', 'c.id', '=', 'v.product_color_id')->where('v.is_active', true)->whereIn('c.product_id', (clone $optionsBase)->select('products.id'));
        $colors = (clone $optionVariants)->distinct()->orderBy('c.name')->pluck('c.name');
        $sizes = (clone $optionVariants)->distinct()->pluck('v.size')->sortBy(fn ($size) => ProductPresentation::sizeKey($size))->values();
        $chips = [];
        foreach (['q', 'category', 'color', 'size', 'in_stock', 'min', 'max'] as $key) {
            foreach ((array) ($filters[$key] ?? []) as $value) {
                $remaining = $filters;
                if (is_array($filters[$key])) {
                    $remaining[$key] = array_values(array_diff($filters[$key], [$value]));
                } else {
                    unset($remaining[$key]);
                }
                $chips[] = ['label' => ($key === 'in_stock' ? 'In stock' : ucfirst($key).': '.$value), 'url' => $request->url().'?'.http_build_query($remaining)];
            }
        }
        $clearUrl = $request->url().'?currency='.$currency;

        return compact('products', 'cards', 'categories', 'colors', 'sizes', 'filters', 'chips', 'clearUrl', 'currency', 'sort', 'noindex');
    }
}
