<?php

namespace App\Jobs;

use App\Models\ProductVariant;
use App\Models\RestockSubscription;
use App\Services\Inventory;
use App\Services\RestockAlerts;
use App\Support\StoreSettings;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SendRestockAlerts implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(public int $variantId)
    {
        $this->onQueue('restock');
    }

    public function uniqueId(): string
    {
        return config('database.connections.'.config('database.default').'.database').':'.$this->variantId;
    }

    public function handle(): void
    {
        RestockSubscription::waiting()->where('product_variant_id', $this->variantId)->select('id')->chunkById(50, function ($rows): void {
            foreach ($rows as $candidate) {
                DB::transaction(function () use ($candidate): void {
                    $variant = ProductVariant::with('productColor.product.category')->lockForUpdate()->find($this->variantId);
                    $row = RestockSubscription::waiting()->lockForUpdate()->find($candidate->id);
                    if (! $row || ! $variant || ! RestockAlerts::publicVariant($variant) || ! Inventory::purchasable($variant, 'PKR', $variant->productColor->product)) {
                        return;
                    }
                    $brand = StoreSettings::brandName();
                    RestockAlerts::mail($row, $brand.' - your size is available', $brand."\n\n".RestockAlerts::label($variant)." is available to order.\n\nOpen this exact color and size:\n".RestockAlerts::buyUrl($variant)."\n\nThis alert does not reserve stock; availability is checked again at checkout. This completes your one-time alert.\nManage or cancel this request:\n".RestockAlerts::manageUrl($row), 'available');
                    $row->update(['completed_at' => now()]);
                });
            }
        });
    }
}
