<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class Fulfillment
{
    public const NEXT = ['pending' => ['confirmed', 'cancelled', 'expired'], 'confirmed' => ['processing', 'cancelled', 'expired'], 'processing' => ['shipped', 'cancelled'], 'shipped' => ['delivered'], 'delivered' => [], 'returned' => [], 'cancelled' => [], 'expired' => []];

    public function transition(int $id, string $target, ?int $actor = null): Order
    {
        return DB::transaction(function () use ($id, $target, $actor): Order {
            $order = Order::with('lines')->lockForUpdate()->findOrFail($id);
            if ($order->status === $target) {
                return $order;
            }
            if (! in_array($target, self::NEXT[$order->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'This order transition is not allowed.']);
            }
            if ($target === 'expired' && ($order->payment_method !== 'bank' || $order->payment_status === 'paid' || ! $order->expires_at?->isPast())) {
                throw ValidationException::withMessages(['status' => 'Only overdue unpaid bank transfers may expire.']);
            }
            if (in_array($target, ['processing', 'shipped'], true) && $order->payment_method === 'bank' && $order->payment_status !== 'paid') {
                throw ValidationException::withMessages(['status' => 'Verify receipt of bank funds before processing.']);
            }
            if ($target === 'shipped' && $order->shipment && $order->shipment->stage !== 'with_courier') {
                throw ValidationException::withMessages(['status' => 'Use Dispatch handover to assign courier and tracking before shipping.']);
            }
            if ($target === 'processing' && $order->requests()->where('type', 'address')->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['status' => 'Review the address correction before packing.']);
            }
            if ($target === 'shipped' && $order->requests()->whereIn('type', ['cancellation', 'address'])->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['status' => 'Review pending cancellation or address correction requests before packing or shipping.']);
            }
            if ($target === 'delivered' && $order->shipment && ! in_array($order->shipment->stage, ['with_courier', 'issue'], true)) {
                throw ValidationException::withMessages(['status' => 'A returning parcel cannot be marked delivered. Receive it at the store.']);
            }
            if (in_array($target, ['cancelled', 'expired', 'shipped'], true)) {
                $variants = ProductVariant::whereIn('id', $order->lines->pluck('product_variant_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($order->lines as $line) {
                    app(Inventory::class)->change($variants[$line->product_variant_id], $target === 'shipped' ? -$line->quantity : 0, -$line->quantity, 'Order '.$target, $order->id, $actor);
                }
            }
            if ($target === 'delivered' && $order->shipment) {
                $order->shipment->update(['stage' => 'delivered', 'version' => $order->shipment->version + 1]);
            }
            $order->update(['status' => $target] + ($target === 'delivered' ? ['delivered_at' => now()] : []));
            $order->histories()->create(['actor_id' => $actor, 'status' => $target, 'payment_status' => $order->payment_status, 'note' => 'Order '.$target]);
            $message = match ($target) {
                'confirmed' => 'Your order is confirmed. We will prepare your items for packing.',
                'processing' => 'We are preparing your order. We will update you when it is packed.',
                'shipped' => 'Your order has been handed to the courier.',
                'delivered' => 'Your order has been delivered. Your private order page shows any available return options.',
                'cancelled' => 'Your order has been cancelled. Any eligible refund is handled separately.',
                'expired' => 'Your payment deadline has passed and the stock reservation has ended. Contact us if you have already paid.',
                default => 'View your order for the latest progress and available next steps.',
            };
            if ($target === 'shipped' && $order->shipment) {
                $message .= ' Courier: '.$order->shipment->courier_name.'. Tracking: '.$order->shipment->tracking_number.'.';
                if ($trackingUrl = $order->shipment->trackingUrl()) {
                    $message .= ' Track parcel: '.$trackingUrl;
                }
            }
            OrderMessages::record($order, 'status-'.$target, 'Order '.$target, $message);

            return $order;
        }, 5);
    }

    public function verifyPayment(int $id, int $actor, string $reference): void
    {
        if (trim($reference) === '' || strlen($reference) > 400) {
            throw ValidationException::withMessages(['payment' => 'Enter the verified receipt/reference.']);
        }
        DB::transaction(function () use ($id, $actor, $reference): void {
            $order = Order::lockForUpdate()->findOrFail($id);
            if ($order->payment_status === 'paid') {
                return;
            }
            if (in_array($order->status, ['cancelled', 'expired'], true) || ($order->payment_method === 'cod' && $order->status !== 'delivered')) {
                throw ValidationException::withMessages(['payment' => 'Payment cannot be recorded in this order state.']);
            }
            if ($order->payment_method === 'cod' && $order->shipment) {
                $order->shipment->update(['collected_minor' => $order->shipment->collected_minor + max(0, $order->total_minor - $order->collected_minor), 'collection_reference' => $reference, 'collection_confirmed_at' => now(), 'version' => $order->shipment->version + 1]);
            }
            $order->update(['payment_status' => 'paid', 'collected_minor' => $order->total_minor]);
            $order->histories()->create(['actor_id' => $actor, 'status' => $order->status, 'payment_status' => 'paid', 'note' => 'Funds verified: '.$reference]);
            OrderMessages::record($order, 'payment-verified', 'Payment verified', 'We have verified receipt of your payment. Follow fulfillment progress using your order link.');
        }, 3);
    }
}
