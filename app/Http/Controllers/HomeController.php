<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Homepage;
use App\Models\Product;
use App\Services\Pricing;
use App\Support\ProductPhoto;
use App\Support\StorefrontCampaigns;
use App\Support\StorefrontSeo;
use App\Support\StoreSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->render($request);
    }

    public function preview(Request $request): Response
    {
        Gate::authorize('manage-commerce');

        return $this->render($request, true)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow, noarchive')->header('Referrer-Policy', 'no-referrer');
    }

    private function render(Request $request, bool $preview = false): Response
    {
        $home = Homepage::with('media')->where('key', 'main')->first();
        $request->attributes->set('store_navigation_home', $home);
        $content = ($preview ? $home?->draft : $home?->published) ?? [];
        $brand = StoreSettings::brandName();
        $headline = $content['headline'] ?? 'Explore the collection';
        $currency = Pricing::currency($request->query('currency', $request->session()->get('currency')));
        $request->session()->put('currency', $currency);
        $ids = collect($content['featured'] ?? [])->pluck('collection_id')->filter()->unique()->values()->all();
        $collections = Collection::where('is_published', true)->whereIn('id', $ids)->whereHas('eligibleItems')->with(['media', 'eligibleItems' => fn ($q) => $q->limit(4)->with(StorefrontCampaigns::cardRelations())])->get()->keyBy('id');
        if (! $preview) {
            $request->attributes->set('store_navigation_collections', $collections);
        }
        $sections = collect($ids)->map(function ($id) use ($collections, $currency): ?array {
            $collection = $collections->get($id);
            if (! $collection) {
                return null;
            }
            $cards = $collection->eligibleItems->map(fn ($item) => StorefrontCampaigns::card($item->product, $currency, $item->featured_color_id));

            return ['cover' => StorefrontCampaigns::cover($collection, $cards->first(fn ($card) => $card['photo'])['photo'] ?? null), 'collection' => $collection, 'cards' => $cards, 'url' => StorefrontCampaigns::url('collections.show', ['collection' => $collection->slug])];
        })->filter()->values();
        $heroMedia = $preview ? $home?->getFirstMedia('draft_hero') : $home?->getMedia('published_heroes')->firstWhere('id', $content['hero_media_id'] ?? null);
        if ($heroMedia) {
            $heroMedia->setRelation('model', $home);
        }
        $hero = $heroMedia ? ProductPhoto::data($heroMedia) : null;
        if ($hero) {
            $hero['alt'] = $content['hero_alt'] ?? '';
        }
        $fallbackCards = collect();
        if (! $hero || $sections->isEmpty()) {
            $fallbackCards = Product::visible()->whereHas('colors.variants', fn ($q) => $q->where('is_active', true))
                ->with(['category', 'colors.variants' => fn ($q) => $q->where('is_active', true), 'colors.media' => fn ($q) => $q->where('collection_name', 'gallery')->orderBy('order_column')->limit(1)])
                ->latest('id')->limit(4)->get()->map(fn ($product) => StorefrontCampaigns::card($product, $currency));
            $hero ??= $fallbackCards->first(fn ($card) => $card['photo'])['photo'] ?? null;
        }
        $primary = ($content['primary_target'] ?? 'shop') === 'collection' ? ($collections->get($content['primary_collection_id'] ?? null) ?? Collection::whereKey($content['primary_collection_id'] ?? null)->where('is_published', true)->whereHas('eligibleItems')->first()) : null;
        $primaryUrl = $primary ? StorefrontCampaigns::url('collections.show', ['collection' => $primary->slug]) : StorefrontCampaigns::url('shop');
        $primaryLabel = $primary ? 'Shop '.$primary->title : 'Shop all products';
        $canonical = StorefrontCampaigns::url('home');
        $metaDescription = Str::limit(Str::squish(strip_tags($content['seo_description'] ?? $content['supporting_text'] ?? 'Explore '.$brand.' and shop our latest collections.')), 155, '');

        if (filled($content['seo_description'] ?? null)) {
            $metaDescription = StorefrontSeo::description($content['seo_description']);
        }
        $seoTitle = ($content['seo_title'] ?? null) ?: $headline.' | '.$brand;

        return response()->view('home', compact('seoTitle', 'preview', 'content', 'headline', 'brand', 'sections', 'hero', 'primaryUrl', 'primaryLabel', 'canonical', 'metaDescription', 'fallbackCards'));
    }
}
