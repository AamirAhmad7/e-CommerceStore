<?php

namespace App\Services;

use App\Models\ProductVariant;

final class InternationalInquiry
{
    /** @return array{shipping: ?int, total: ?int, wording: string} */
    public static function shipping(int $subtotal, int $quantity, string $destination, array $settings): array
    {
        $mode = $settings['international_shipping_mode'] ?? 'quote';
        if (trim($destination) !== '' && $mode === 'included') {
            return ['shipping' => 0, 'total' => $subtotal, 'wording' => 'Shipping included in the international selling prices.'];
        }
        foreach ($settings['international_shipping_rates'] ?? [] as $rate) {
            if ($mode === 'separate' && mb_strtolower(trim($rate['destination'] ?? '')) === mb_strtolower(trim($destination)) && trim($destination) !== '' && isset($rate['amount']) && preg_match('/^\d{1,6}(?:\.\d{1,2})?$/', (string) $rate['amount'])) {
                $shipping = Pricing::parse((string) $rate['amount'], 'USD') * (($rate['basis'] ?? 'order') === 'item' ? $quantity : 1);

                return ['shipping' => $shipping, 'total' => $subtotal + $shipping, 'wording' => 'Estimated shipping charged separately (per '.(($rate['basis'] ?? 'order') === 'item' ? 'jacket' : 'order').'). Final quote confirmed by the store.'];
            }
        }

        return ['shipping' => null, 'total' => null, 'wording' => 'Request a shipping quote'];
    }

    /** @param array<int, int> $items */
    public static function quote(array $items): array
    {
        $variants = ProductVariant::with('productColor.product.category')->whereIn('id', array_keys($items))->get()->keyBy('id');
        $lines = [];
        $subtotal = 0;
        $valid = count($items) > 0;
        foreach ($items as $id => $quantity) {
            $variant = $variants->get($id);
            $product = $variant?->productColor?->product;
            $price = $variant && $product ? Pricing::resolve($variant, 'USD', $product) : null;
            $available = $variant ? Inventory::available($variant) : 0;
            $ready = $product && $product->is_active && $product->category?->is_active && $variant->is_active && $price > 0 && $available >= $quantity;
            $valid = $valid && $ready;
            $subtotal += ($price ?? 0) * $quantity;
            $lines[] = compact('id', 'variant', 'product', 'price', 'quantity', 'available', 'ready');
        }

        return compact('lines', 'subtotal', 'valid');
    }
}
