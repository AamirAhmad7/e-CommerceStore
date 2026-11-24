<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Services\OrderMessages;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OrderNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function __construct(public int $orderId, public ?int $eventId = null) {}

    public function handle(): void
    {
        if (! isset($this->eventId) && Order::findOrFail($this->orderId)->notification_logged_at) {
            return;
        }
        $eventId = $this->eventId ?? OrderMessages::confirmation($this->orderId)->id;
        try {
            DB::transaction(function () use ($eventId): void {
                $event = OrderEvent::lockForUpdate()->findOrFail($eventId);
                if ($event->sent_at || ! filter_var($event->recipient, FILTER_VALIDATE_EMAIL)) {
                    return;
                }
                $database = config('database.connections.'.config('database.default').'.database');
                $mailer = $database === 'leather_store_demo' ? 'log' : config('mail.default');
                Mail::mailer($mailer)->raw(html_entity_decode($event->body, ENT_QUOTES | ENT_HTML5, 'UTF-8'), function ($message) use ($event): void {
                    $message->to($event->recipient)->subject($event->subject);
                    $message->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', 'order-event-'.$event->id.'@'.parse_url(config('app.url'), PHP_URL_HOST));
                });
                $event->update(['sent_at' => now(), 'last_error' => null, 'attempts' => $event->attempts + 1]);
                if ($event->event_key === 'confirmation') {
                    Order::whereKey($event->order_id)->update(['notification_logged_at' => now()]);
                }
            });
        } catch (Throwable $e) {
            OrderEvent::whereKey($eventId)->update(['last_error' => mb_substr($e->getMessage(), 0, 2000), 'attempts' => DB::raw('attempts + 1')]);
            throw $e;
        }
    }
}
