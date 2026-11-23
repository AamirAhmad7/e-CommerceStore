<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing;
use App\Support\CatalogDiscovery;
use App\Support\StorefrontSeo;
use App\Support\StoreSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function suggestions(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => 'nullable|string|max:160', 'currency' => 'nullable|in:PKR,USD']);
        $term = Str::lower(Str::squish($data['q'] ?? ''));
        $currency = Pricing::currency($data['currency'] ?? null);
        $resultsUrl = route('shop', ['q' => $data['q'] ?? '', 'currency' => $currency]);
        if (mb_strlen($term) < 2) {
            return response()->json(['items' => [], 'results_url' => $resultsUrl])->header('Cache-Control', 'private, no-store');
        }
        $query = Product::visible()->whereHas('variants', fn ($variants) => $variants->where('product_variants.is_active', true));
        CatalogDiscovery::search($query, $term);
        $exactVariant = ProductVariant::query()->join('product_colors', 'product_colors.id', '=', 'product_variants.product_color_id')
            ->whereColumn('product_colors.product_id', 'products.id')->where('product_variants.is_active', true)->whereRaw('LOWER(product_variants.sku) = ?', [$term])->orderBy('product_variants.id')->limit(1);
        $items = $query->select(['products.id', 'products.name', 'products.slug'])
            ->selectSub((clone $exactVariant)->select('product_variants.product_color_id'), 'matched_color')
            ->selectSub((clone $exactVariant)->select('product_variants.size'), 'matched_size')
            ->orderByRaw('CASE WHEN LOWER(products.name) = ? THEN 0 ELSE 1 END', [$term])->orderBy('products.name')->orderBy('products.id')->limit(6)->get()
            ->map(fn ($product): array => ['name' => $product->name, 'url' => route('products.show', array_filter(['product' => $product->slug, 'currency' => $currency, 'color' => $product->matched_color, 'size' => $product->matched_size], fn ($value) => $value !== null))]);

        return response()->json(['items' => $items, 'results_url' => $resultsUrl])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function index(Request $request, ?Category $category = null): View
    {
        if ($category) {
            abort_unless($category->is_active, 404);
        }
        $discovery = app(CatalogDiscovery::class)->browse($request, $category);
        $brand = StoreSettings::brandName();
        $home = $request->routeIs('home');
        $title = $category ? $category->name : ($home ? $brand : 'Shop');
        $canonical = $category ? route('categories.show', $category->slug) : route($home ? 'home' : 'shop');
        if ($request->integer('page') > 1) {
            $canonical .= '?page='.$request->integer('page');
        }

        $seoTitle = $category?->seo_title ?: $title.' | '.$brand;
        $metaDescription = $category?->seo_description ?: StorefrontSeo::description($category?->description ?: 'Browse '.$brand.' products by color, size and availability.');

        return view('shop.index', $discovery + compact('seoTitle', 'metaDescription', 'category', 'brand', 'home', 'title', 'canonical'));
    }
}
