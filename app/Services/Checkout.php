<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Payments\Gateways;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Checkout
{
    public function create(string $guest, string $key, array $customer, array $review): Order
    {
        $hash = hash('sha256', json_encode([$guest, $customer, $review], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($guest, $key, $customer, $review, $hash): Order {
            DB::table('checkout_attempts')->insertOrIgnore(['key' => $key, 'guest_hash' => $guest, 'payload_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            $attempt = DB::table('checkout_attempts')->where('key', $key)->lockForUpdate()->first();
            if (! hash_equals($attempt->guest_hash, $guest) || ! hash_equals($attempt->payload_hash, $hash)) {
                throw ValidationException::withMessages(['checkout' => 'This checkout key was already used for different details. Start a new review.']);
            }
            if ($attempt->order_id) {
                return Order::findOrFail($attempt->order_id);
            }
            $items = $review['items'];
            ksort($items);
            if (! $items || count($items) > 50) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty or too large.']);
            }
            foreach ($items as $quantity) {
                if (! is_int($quantity) || $quantity < 1 || $quantity > 100) {
                    throw ValidationException::withMessages(['cart' => 'Invalid cart quantity.']);
                }
            }
            $variants = ProductVariant::with('productColor')->whereIn('id', array_keys($items))->orderBy('id')->lockForUpdate()->get();
            $productIds = $variants->map(fn ($v) => $v->productColor->product_id)->unique()->sort()->values();
            $products = Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $categories = Category::whereIn('id', $products->pluck('category_id')->unique()->sort())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($products as $product) {
                $product->setRelation('category', $categories->get($product->category_id));
            }
            foreach ($variants as $variant) {
                $variant->productColor->setRelation('product', $products->get($variant->productColor->product_id));
            }
            $quote = app(Cart::class)->quote($items, 'PKR', $variants->keyBy('id'));
            if (! $quote['valid']) {
                $unavailable = collect($quote['lines'])->filter(fn ($line) => ! $line['valid'])->map(fn ($line): string => ($line['variant']?->sku ?? 'Removed SKU').' (requested '.$line['quantity'].', available '.$line['available'].')')->implode('; ');
                throw ValidationException::withMessages(['checkout' => 'These items are unavailable, lack a PKR price, or have insufficient stock: '.$unavailable.'. Review your selection or ask the store for an update.']);
            }
            $settingsRow = DB::table('store_settings')->where('key', 'commerce')->lockForUpdate()->first();
            $settings = array_replace(CommerceSettings::defaults(), $settingsRow ? json_decode($settingsRow->value, true, 512, JSON_THROW_ON_ERROR) : []);
            $promotion = app(Promotions::class)->resolve($quote, $settings, $review['promotion_code'] ?? null, true);
            $totals = $promotion['totals'];
            if (! hash_equals($review['fingerprint'], Cart::fingerprint($quote, $totals)) || (array_key_exists('promotion_snapshot', $review) && $review['promotion_snapshot'] !== $promotion['snapshot'])) {
                throw ValidationException::withMessages(['checkout' => 'Prices, delivery charges or offer availability changed. Nothing was ordered. Review the revised PKR total and confirm again.']);
            }
            $gateway = Gateways::get($customer['payment_method'], $settings);
            $order = Order::create($totals + ['number' => 'SF-'.strtoupper((string) Str::ulid()), 'guest_hash' => $guest, 'access_token' => Str::random(64), 'customer' => $customer, 'status' => 'pending', 'payment_method' => $gateway->code(), 'payment_status' => 'unpaid', 'payment_instructions' => $gateway->instructions($settings), 'expires_at' => $gateway->code() === 'bank' ? now()->addHours((int) $settings['expiry_hours']) : null]);
            if ($promotion['snapshot']) {
                $order->update(['promotion_id' => $promotion['snapshot']['id'], 'promotion_snapshot' => $promotion['snapshot']]);
                DB::table('promotions')->where('id', $promotion['snapshot']['id'])->increment('uses');
                DB::table('promotion_redemptions')->insert(['promotion_id' => $promotion['snapshot']['id'], 'order_id' => $order->id, 'eligible_units' => $promotion['snapshot']['eligible_units'], 'eligible_minor' => $promotion['snapshot']['eligible_minor'], 'merchandise_discount_minor' => $promotion['snapshot']['merchandise_discount_minor'], 'shipping_discount_minor' => $promotion['snapshot']['shipping_discount_minor'], 'created_at' => now(), 'updated_at' => now()]);
            }
            $locked = $variants->keyBy('id');
            foreach ($quote['lines'] as $line) {
                $v = $locked->get($line['id']);
                $allocation = $promotion['lines'][$line['id']] ?? null;
                $order->lines()->create(($allocation ? ['promotion_discount_minor' => $allocation['discount'], 'promotion_eligible' => $allocation['eligible'], 'unit_paid_minor' => $allocation['unit_paid']] : []) + ['product_variant_id' => $v->id, 'product_name' => $line['product']->name, 'color' => $v->productColor->name, 'size' => $v->size, 'sku' => $v->sku, 'quantity' => $line['quantity'], 'unit_price_minor' => $line['price'], 'total_minor' => $line['total'], 'currency' => 'PKR']);
                app(Inventory::class)->change($v, 0, $line['quantity'], 'Order reservation', $order->id);
            }
            $order->histories()->create(['status' => 'pending', 'payment_status' => 'unpaid', 'note' => 'Order placed; stock reserved.']);
            DB::table('checkout_attempts')->where('key', $key)->update(['order_id' => $order->id]);
            OrderMessages::confirmation($order->id);

            return $order;
        }, 5);
    }
}
