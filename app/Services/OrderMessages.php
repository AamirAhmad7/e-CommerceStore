<?php

namespace App\Services;

use App\Jobs\OrderNotification;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Support\StoreSettings;
use Illuminate\Support\Facades\DB;

final class OrderMessages
{
    /** Caller holds the order lock; the outbox commits with the business event. */
    public static function record(Order $order, string $key, string $subject, string $message, bool $visible = true): OrderEvent
    {
        $event = $order->events()->firstOrCreate(['event_key' => $key], [
            'subject' => $subject.' - '.$order->number, 'message' => $message, 'customer_visible' => $visible,
            'recipient' => $order->customer['email'],
            'body' => view('mail.order-event', ['order' => $order->loadMissing('lines'), 'messageText' => $message, 'brand' => StoreSettings::brandName(), 'contact' => CommerceSettings::get()['contact_email']])->render(),
        ]);
        if ($event->wasRecentlyCreated) {
            self::queue($event);
        }

        return $event;
    }

    public static function queue(OrderEvent $event): void
    {
        OrderNotification::dispatch($event->order_id, $event->id)->onConnection('database')->onQueue('default')->afterCommit();
    }

    public static function confirmation(int $orderId): OrderEvent
    {
        return DB::transaction(function () use ($orderId): OrderEvent {
            $order = Order::lockForUpdate()->findOrFail($orderId);

            return self::record($order, 'confirmation', 'Order received', 'Your order was received. Keep the private link below to follow its progress. Payment and order progress are shown separately.');
        });
    }
}
