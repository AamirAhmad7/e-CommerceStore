<?php

namespace App\Support;

use App\Models\Order;

final class StorefrontEvents
{
    public static function purchase(Order $order): array
    {
        return ['event' => 'purchase', 'currency' => 'PKR', 'transaction_id' => hash_hmac('sha256', 'order:'.$order->id, config('app.key')), 'value' => ($order->total_minor - $order->shipping_minor - $order->tax_minor) / 100, 'shipping' => $order->shipping_minor / 100, 'tax' => $order->tax_minor / 100, 'items' => $order->lines->map(fn ($line): array => ['item_id' => (string) $line->product_variant_id, 'item_name' => $line->product_name, 'item_variant' => $line->color.' / '.$line->size, 'price' => ($line->total_minor - ($line->promotion_discount_minor ?? 0)) / $line->quantity / 100, 'quantity' => $line->quantity])->all()];
    }

    public static function checkout(array $quote, array $totals): array
    {
        return ['event' => 'begin_checkout', 'currency' => 'PKR', 'value' => ($totals['total_minor'] - $totals['shipping_minor'] - $totals['tax_minor']) / 100, 'items' => array_map(fn ($line): array => ['item_id' => (string) $line['id'], 'item_name' => $line['product']->name, 'price' => $line['price'] / 100, 'quantity' => $line['quantity']], $quote['lines'])];
    }
}
