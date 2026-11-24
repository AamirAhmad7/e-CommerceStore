<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart;
use App\Services\Pricing;
use App\Support\StorefrontCampaigns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedJacketsController extends Controller
{
    public function show(Request $request): View
    {
        $saved = $request->session()->get('saved_jackets', []);
        $recent = $request->session()->get('recent_jackets', []);
        $products = Product::visible()->whereIn('id', array_unique([...$saved, ...$recent]))
            ->with(['colors.variants', 'colors.media'])->get()->keyBy('id');
        $currency = Pricing::currency($request->session()->get('currency'));
        $cards = collect($saved)->filter(fn ($id) => $products->has($id))->map(fn ($id) => StorefrontCampaigns::card($products[$id], $currency));
        $recentCards = collect($recent)->filter(fn ($id) => $products->has($id) && ! in_array($id, $saved))->map(fn ($id) => StorefrontCampaigns::card($products[$id], $currency));
        $data = $request->validate(['compare' => 'sometimes|array|max:3', 'compare.*' => 'integer|distinct']);
        $selectedIds = array_map('intval', $data['compare'] ?? $cards->take(3)->pluck('product.id')->all());
        $comparison = $cards->filter(fn ($card) => in_array($card['product']->id, $selectedIds))->take(3);

        return view('shop.saved-jackets', compact('cards', 'recentCards', 'comparison', 'currency', 'selectedIds') + ['noindex' => true]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['action' => 'required|in:save,remove']);
        $ids = $request->session()->get('saved_jackets', []);
        if ($data['action'] === 'remove') {
            $ids = array_values(array_diff($ids, [$product->id]));
        } else {
            abort_unless(Product::visible()->whereKey($product->id)->exists(), 404);
            if (! in_array($product->id, $ids)) {
                if (count($ids) >= 20) {
                    return back()->withErrors(['saved' => 'Your wishlist holds 20 jackets. Remove one before saving another.']);
                }
                $ids[] = $product->id;
            }
        }
        $request->session()->put('saved_jackets', $ids);

        return back()->with('status', $data['action'] === 'save' ? 'Jacket saved to your wishlist.' : 'Jacket removed from your wishlist.');
    }

    public function saveCartItem(Request $request, ProductVariant $variant, Cart $cart): RedirectResponse
    {
        abort_unless(array_key_exists($variant->id, $cart->items($request)), 404);
        $product = $variant->productColor->product;
        abort_unless(Product::visible()->whereKey($product->id)->exists(), 404);
        $ids = $request->session()->get('saved_jackets', []);
        if (! in_array($product->id, $ids) && count($ids) >= 20) {
            return back()->withErrors(['saved' => 'Your wishlist is full. Remove a saved jacket first. Your cart has not changed.']);
        }
        $request->session()->put('saved_jackets', array_values(array_unique([...$ids, $product->id])));
        $cart->set($request, $variant->id, 0);

        return redirect()->route('cart.show')->with('status', 'Jacket saved for later and removed from your cart. Choose its size again from Saved jackets when ready.');
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->session()->forget('recent_jackets');

        return redirect()->route('saved.show')->with('status', 'Recently viewed history cleared.');
    }
}
