<?php

namespace App\Http\Controllers;

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
            if ($settings['shipping_minor'] !== null && $settings['tax_basis_points'] !== null) {
                $promotion = app(Promotions::class)->resolve($quote, $settings, $request->session()->get('promotion_code'));
            }
        }

        return view('shop.cart', compact('quote', 'changed', 'promotion'));
    }

    public function add(Request $request, Cart $cart)
    {
        $data = $request->validate(['variant_id' => 'required|integer', 'product_id' => 'required|integer', 'quantity' => 'required|integer|min:1|max:100']);
        $owned = ProductVariant::whereKey($data['variant_id'])->whereHas('productColor', fn ($q) => $q->where('product_id', $data['product_id']))->exists();
        if (! $owned) {
            throw ValidationException::withMessages(['cart' => 'This variant does not belong to the selected product.']);
        }
        try {
            $cart->set($request, $data['variant_id'], $data['quantity'], true);
        } catch (ValidationException $exception) {
            $variant = ProductVariant::with('productColor.product')->findOrFail($data['variant_id']);

            return redirect()->route('products.show', ['product' => $variant->productColor->product->slug, 'color' => $variant->product_color_id, 'size' => $variant->size])->withErrors($exception->errors());
        }

        return redirect()->route('cart.show')->with('status', 'Added to your cart. Review your selection below.');
    }

    public function update(Request $request, Cart $cart, int $variant)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:0|max:100']);
        abort_unless(array_key_exists($variant, $cart->items($request)), 404);
        $cart->set($request, $variant, $data['quantity']);

        return redirect()->route('cart.show')->with('status', $data['quantity'] === 0 ? 'Item removed.' : 'Quantity updated.');
    }
}
