<?php

namespace App\Jobs;

use App\Models\RestockSubscription;
use App\Services\RestockAlerts;
use App\Support\StoreSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ConfirmRestockEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 60;

    public function __construct(public int $subscriptionId, public string $tokenHash)
    {
        $this->onQueue('restock');
    }

    public function handle(): void
    {
        DB::transaction(function (): void {
            $row = RestockSubscription::lockForUpdate()->find($this->subscriptionId);
            if (! $row || $row->token_hash !== $this->tokenHash || $row->confirmation_sent_at || $row->confirmed_at || $row->cancelled_at || $row->completed_at || $row->confirmation_expires_at->isPast()) {
                return;
            }
            $variant = $row->variant()->with('productColor.product.category')->first();
            if (! $variant || ! RestockAlerts::publicVariant($variant)) {
                return;
            }
            $brand = StoreSettings::brandName();
            RestockAlerts::mail($row, $brand.' - confirm your size alert', $brand."\n\nConfirm one back-in-stock alert for ".RestockAlerts::label($variant).".\n\nOpen this private link, then press Confirm alert:\n".RestockAlerts::manageUrl($row)."\n\nThis link expires in seven days. Opening it does not confirm anything. You can cancel using the same private page. This is not a marketing subscription and does not reserve stock. If you did not request this, ignore this email.", 'confirm');
            $row->update(['confirmation_sent_at' => now()]);
        });
    }
}
