<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Support\CatalogDiscovery;
use App\Support\StoreSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
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

        return view('shop.index', $discovery + compact('category', 'brand', 'home', 'title', 'canonical'));
    }
}
