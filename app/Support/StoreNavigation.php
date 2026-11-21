<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Homepage;

final class StoreNavigation
{
    /** @return array<int,array{label:string,url:string,active:bool}> */
    public static function links(): array
    {
        $request = request();
        if ($request->attributes->has('store_navigation')) {
            return $request->attributes->get('store_navigation');
        }
        $home = $request->attributes->has('store_navigation_home') ? $request->attributes->get('store_navigation_home') : Homepage::where('key', 'main')->first(['published']);
        $ids = collect($home?->published['featured'] ?? [])->pluck('collection_id')->filter()->unique()->take(4);
        $collections = Collection::whereIn('id', $ids)->where('is_published', true)->whereHas('eligibleItems')->get(['id', 'slug', 'title'])->keyBy('id');
        $links = [];
        foreach ($ids as $id) {
            if ($c = $collections->get($id)) {
                $links[] = ['label' => $c->title, 'url' => route('collections.show', $c->slug), 'active' => $request->routeIs('collections.show') && $request->route('collection')?->id === $c->id];
            }
        }
        if (! $links) {
            foreach (Category::where('is_active', true)->whereHas('products', fn ($q) => $q->visible())->orderBy('name')->limit(4)->get(['id', 'slug', 'name']) as $c) {
                $links[] = ['label' => $c->name, 'url' => route('categories.show', $c->slug), 'active' => $request->routeIs('categories.show') && $request->route('category')?->id === $c->id];
            }
        }
        $request->attributes->set('store_navigation', $links);

        return $links;
    }
}
