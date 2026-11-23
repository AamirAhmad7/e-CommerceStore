<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart;
use App\Services\CommerceSettings;
use App\Services\Pricing;
use App\Services\Promotions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function show(Request $request, Cart $cart)
    {
        $cart->guest($request);
        $currency = Pricing::currency($request->session()->get('currency'));
        $quote = $cart->quote($cart->items($request), $currency);
        $snapshot = array_map(fn ($l) => [$l['id'], $l['price'], $l['available']], $quote['lines']);
        $changed = $request->session()->has('cart_snapshot') && $request->session()->get('cart_snapshot') !== $snapshot;
        $request->session()->put('cart_snapshot', $snapshot);

        $promotion = null;
        if ($currency === 'PKR' && $quote['lines']) {
            $settings = CommerceSettings::get();
            if (CommerceSettings::quoteReady($quote)) {
                $promotion = app(Promotions::class)->resolve($quote, $settings, $request->session()->get('promotion_code'));
            }
        }

        return view('shop.cart', compact('quote', 'changed', 'promotion'));
    }

    public function add(Request $request, Cart $cart)
    {
        $data = $request->validate(['variant_id' => 'required|integer', 'product_id' => 'required|integer', 'quantity' => 'required|integer|min:1|max:100', 'currency' => 'nullable|in:PKR,USD', 'expected_price' => 'nullable|integer|min:1']);
        $owned = ProductVariant::whereKey($data['variant_id'])->whereHas('productColor', fn ($q) => $q->where('product_id', $data['product_id']))->exists();
        if (! $owned) {
            throw ValidationException::withMessages(['cart' => 'This variant does not belong to the selected product.']);
        }
        try {
            if (! CommerceSettings::checkoutReady(Product::find($data['product_id']))) {
                throw ValidationException::withMessages(['cart' => 'Please contact the store to arrange your order.']);
            }
            if ($request->expectsJson()) {
                $currency = Pricing::currency($data['currency'] ?? $request->session()->get('currency'));
                $variant = ProductVariant::with('productColor.product')->findOrFail($data['variant_id']);
                if (isset($data['expected_price']) && Pricing::resolve($variant, $currency) !== (int) $data['expected_price']) {
                    throw ValidationException::withMessages(['cart' => 'The price changed. Review the updated price and press Add to cart again.']);
                }
                $request->session()->put('currency', $currency);
            }
            $cart->set($request, $data['variant_id'], $data['quantity'], true);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }
            $variant = ProductVariant::with('productColor.product')->findOrFail($data['variant_id']);

            return redirect()->route('products.show', ['product' => $variant->productColor->product->slug, 'color' => $variant->product_color_id, 'size' => $variant->size])->withErrors($exception->errors());
        }

        if ($request->expectsJson()) {
            $variant = ProductVariant::with('productColor')->findOrFail($data['variant_id']);

            return response()->json(['message' => $variant->productColor->name.' / '.$variant->size.' added to your cart', 'count' => array_sum($cart->items($request)), 'cart_url' => route('cart.show'), 'analytics' => ['event' => 'add_to_cart', 'currency' => 'PKR', 'value' => Pricing::resolve($variant, 'PKR') * (int) $data['quantity'] / 100, 'items' => [['item_id' => (string) $variant->id, 'item_name' => $variant->productColor->product->name, 'item_variant' => $variant->productColor->name.' / '.$variant->size, 'price' => Pricing::resolve($variant, 'PKR') / 100, 'quantity' => (int) $data['quantity']]]]]);
        }

        return redirect()->route('cart.show')->with('status', 'Added to your cart. Review your selection below.');
    }

    public function update(Request $request, Cart $cart, int $variant)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:0|max:100', 'after_update' => 'nullable|in:cart,checkout']);
        abort_unless(array_key_exists($variant, $cart->items($request)), 404);
        $cart->set($request, $variant, $data['quantity']);

        return redirect()->route(($data['after_update'] ?? null) === 'checkout' ? 'checkout.show' : 'cart.show')->with('status', $data['quantity'] === 0 ? 'Item removed.' : 'Quantity updated.');
    }
}
