<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Fulfillment;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ExpireOrders extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Release reservations for overdue unpaid bank transfer orders';

    public function handle(Fulfillment $fulfillment): int
    {
        Order::where('payment_method', 'bank')->where('payment_status', '!=', 'paid')->whereIn('status', ['pending', 'confirmed'])->where('expires_at', '<=', now())->select('id')->chunkById(100, function ($orders) use ($fulfillment): void {
            foreach ($orders as $order) {
                try {
                    $fulfillment->transition($order->id, 'expired');
                } catch (ValidationException) { /* A concurrent payment/transition won the lock. */
                }
            }
        });

        return self::SUCCESS;
    }
}
