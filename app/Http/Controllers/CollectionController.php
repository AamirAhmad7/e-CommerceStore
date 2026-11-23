<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Homepage;
use App\Models\Product;
use App\Support\CatalogDiscovery;
use App\Support\StorefrontCampaigns;
use App\Support\StoreSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class CollectionController extends Controller
{
    public function show(Request $request, Collection $collection): Response
    {
        abort_unless($collection->is_published, 404);

        return $this->render($request, $collection);
    }

    public function preview(Request $request, Collection $collection): Response
    {
        Gate::authorize('manage-commerce');

        return $this->render($request, $collection, true)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow, noarchive')->header('Referrer-Policy', 'no-referrer');
    }

    private function render(Request $request, Collection $collection, bool $preview = false): Response
    {
        $discovery = app(CatalogDiscovery::class)->browse($request, collection: $collection);
        $cards = $discovery['cards'];
        $items = $discovery['products'];
        $collection->load('media');
        $cover = StorefrontCampaigns::cover($collection, $cards->first(fn ($card) => $card['photo'])['photo'] ?? null);
        $canonical = StorefrontCampaigns::url('collections.show', ['collection' => $collection->slug]);
        $metaDescription = $collection->seo_description ?: StorefrontCampaigns::description($collection);
        $seoTitle = $collection->seo_title ?: $collection->title.' | '.StoreSettings::brandName();

        return response()->view('collections.show', $discovery + compact('seoTitle', 'collection', 'preview', 'items', 'cover', 'canonical', 'metaDescription'));
    }

    public function sitemap(): Response
    {
        $home = Homepage::where('key', 'main')->first(['published_at']);
        $urls = [['loc' => StorefrontCampaigns::url('home'), 'lastmod' => $home?->published_at?->toAtomString()], ['loc' => StorefrontCampaigns::url('shop'), 'lastmod' => null]];
        foreach (['about', 'contact', 'faq', 'privacy', 'terms'] as $page) {
            $urls[] = ['loc' => route('information.'.$page), 'lastmod' => null];
        }
        foreach (Product::visible()->whereHas('variants', fn ($q) => $q->where('product_variants.is_active', true))->select(['id', 'slug', 'updated_at'])->withMax('variants', 'updated_at')->lazyById(500) as $product) {
            $changed = max($product->updated_at->toAtomString(), $product->variants_max_updated_at ? Carbon::parse($product->variants_max_updated_at)->toAtomString() : '');
            $urls[] = ['loc' => StorefrontCampaigns::url('products.show', ['product' => $product->slug]), 'lastmod' => $changed];
        }
        foreach (Category::where('is_active', true)->whereHas('products', fn ($q) => $q->visible())->select(['id', 'slug', 'updated_at'])->lazyById(500) as $category) {
            $urls[] = ['loc' => StorefrontCampaigns::url('categories.show', ['category' => $category->slug]), 'lastmod' => $category->updated_at?->toAtomString()];
        }
        foreach (Collection::where('is_published', true)->whereHas('eligibleItems')->select(['id', 'slug', 'updated_at'])->lazyById(500) as $collection) {
            $urls[] = ['loc' => StorefrontCampaigns::url('collections.show', ['collection' => $collection->slug]), 'lastmod' => $collection->updated_at?->toAtomString()];
        }

        return response()->view('sitemap', compact('urls'))->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
