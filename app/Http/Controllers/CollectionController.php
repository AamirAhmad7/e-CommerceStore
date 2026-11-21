<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Support\CatalogDiscovery;
use App\Support\StorefrontCampaigns;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $metaDescription = StorefrontCampaigns::description($collection);

        return response()->view('collections.show', $discovery + compact('collection', 'preview', 'items', 'cover', 'canonical', 'metaDescription'));
    }

    public function sitemap(): Response
    {
        $urls = [StorefrontCampaigns::url('home'), StorefrontCampaigns::url('shop')];
        foreach (Product::visible()->whereHas('colors.variants', fn ($q) => $q->where('is_active', true))->pluck('slug') as $slug) {
            $urls[] = StorefrontCampaigns::url('products.show', ['product' => $slug]);
        }
        foreach (Category::where('is_active', true)->whereHas('products', fn ($q) => $q->visible())->pluck('slug') as $slug) {
            $urls[] = StorefrontCampaigns::url('categories.show', ['category' => $slug]);
        }
        foreach (Collection::where('is_published', true)->whereHas('eligibleItems')->pluck('slug') as $slug) {
            $urls[] = StorefrontCampaigns::url('collections.show', ['collection' => $slug]);
        }

        return response()->view('sitemap', compact('urls'))->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
