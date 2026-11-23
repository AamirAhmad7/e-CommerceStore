<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Cart
{
    public function guest(Request $request): string
    {
        if (! $request->session()->has('guest_secret')) {
            $request->session()->put('guest_secret', Str::random(64));
        }

        return hash('sha256', $request->session()->get('guest_secret'));
    }

    public function items(Request $request): array
    {
        return $request->session()->get('cart', []);
    }

    public function set(Request $request, int $variantId, int $quantity, bool $merge = false): void
    {
        $items = $this->items($request);
        if ($quantity === 0) {
            unset($items[$variantId]);
            $request->session()->put('cart', $items);

            return;
        }
        $v = ProductVariant::with('productColor.product.category')->find($variantId);
        if (! $v || ! $v->is_active || ! $v->productColor->product?->is_active || ! $v->productColor->product->category?->is_active) {
            throw ValidationException::withMessages(['cart' => 'This variant is not available.']);
        }
        $quantity += $merge ? (int) ($items[$variantId] ?? 0) : 0;
        $available = Inventory::available($v);
        if ($quantity > $available) {
            $inCart = (int) ($items[$variantId] ?? 0);
            $message = $available === 1 && $merge && $inCart >= 1
                ? "This is the last piece and it's already in your cart."
                : ($available === 0 ? 'This size just sold out.' : 'Only '.$available.' '.($available === 1 ? 'piece is' : 'pieces are').' available in this size.'.($merge && $inCart > 0 ? ' You already have '.$inCart.' in your cart.' : ''));
            throw ValidationException::withMessages(['cart' => $message]);
        }
        if ($quantity < 1 || $quantity > 100) {
            throw ValidationException::withMessages(['cart' => 'Enter a quantity from 1 to 100. For a larger order, please contact the store.']);
        }
        $currency = Pricing::currency($request->session()->get('currency'));
        if (! Inventory::purchasable($v, $currency)) {
            throw ValidationException::withMessages(['cart' => 'This size is currently unavailable. Choose another size or contact the store.']);
        }
        if (! isset($items[$variantId]) && count($items) >= 50) {
            throw ValidationException::withMessages(['cart' => 'A cart can contain at most 50 different variants.']);
        }
        $items[$variantId] = $quantity;
        ksort($items);
        $request->session()->put('cart', $items);
    }

    public function quote(array $items, string $currency = 'PKR', ?Collection $lockedVariants = null): array
    {
        $variants = $lockedVariants ?? ProductVariant::with(['productColor.product.category', 'productColor.media'])->whereIn('id', array_keys($items))->get()->keyBy('id');
        $lines = [];
        $total = 0;
        $valid = count($items) > 0;
        foreach ($items as $id => $quantity) {
            $v = $variants->get($id);
            $p = $v?->productColor?->product;
            $price = $v && $p ? Pricing::resolve($v, $currency, $p) : null;
            $available = $v ? Inventory::available($v) : 0;
            $ok = $p && $p->is_active && $p->category?->is_active && Inventory::purchasable($v, $currency, $p) && $available >= $quantity;
            $valid = $valid && $ok;
            $lineTotal = $price === null ? null : $price * (int) $quantity;
            $total += $lineTotal ?? 0;
            $lines[] = ['id' => (int) $id, 'variant' => $v, 'product' => $p, 'quantity' => (int) $quantity, 'price' => $price, 'total' => $lineTotal, 'available' => $available, 'valid' => (bool) $ok];
        }

        return ['lines' => $lines, 'subtotal' => $total, 'valid' => $valid, 'currency' => $currency];
    }

    public static function fingerprint(array $quote, array $totals): string
    {
        return hash('sha256', json_encode([array_map(fn ($line) => [$line['id'], $line['quantity'], $line['price']], $quote['lines']), $totals], JSON_THROW_ON_ERROR));
    }
}
