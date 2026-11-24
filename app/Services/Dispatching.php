<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\DispatchManifest;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Dispatching
{
    public static function readyToPack(): Builder
    {
        return Order::query()->whereIn('status', ['pending', 'confirmed', 'processing'])->whereDoesntHave('shipment')
            ->where(fn ($query) => $query->where('payment_method', '!=', 'bank')->orWhere('payment_status', 'paid'))
            ->whereDoesntHave('requests', fn ($query) => $query->whereIn('type', ['cancellation', 'address'])->where('status', 'pending'));
    }

    public static function collectible(Order $order): int
    {
        return $order->payment_method === 'cod' ? max(0, (int) $order->total_minor - (int) $order->collected_minor) : 0;
    }

    public static function blocked(Order $order): ?string
    {
        if (! in_array($order->status, ['pending', 'confirmed', 'processing'], true)) {
            return 'Order is '.$order->status.'.';
        }
        if ($order->payment_method === 'bank' && $order->payment_status !== 'paid') {
            return 'Verify bank funds before packing.';
        }
        if ($order->requests()->whereIn('type', ['cancellation', 'address'])->where('status', 'pending')->exists()) {
            return 'Review the pending cancellation or address correction request first.';
        }

        return null;
    }

    public static function snapshot(Order $order): string
    {
        return Crypt::encryptString(json_encode(self::state($order->fresh()), JSON_THROW_ON_ERROR));
    }

    private static function state(Order $order): array
    {
        return ['id' => $order->id, 'status' => $order->status, 'payment' => $order->payment_status, 'history' => $order->histories()->max('id'), 'version' => $order->shipment?->version ?? 0, 'request' => $order->requests()->max('id')];
    }

    private static function check(Order $order, string $snapshot): void
    {
        if (self::state($order) !== json_decode(Crypt::decryptString($snapshot), true, flags: JSON_THROW_ON_ERROR)) {
            self::invalid($order->number.': Another action changed this order. Reopen the action to review the latest state.');
        }
    }

    public function pack(int $id, string $snapshot, array $quantities): Shipment
    {
        Gate::authorize('manage-commerce');
        Validator::make(['quantities' => $quantities], ['quantities' => 'required|array', 'quantities.*' => 'required|integer|min:1|max:100'])->validate();

        return DB::transaction(function () use ($id, $snapshot, $quantities): Shipment {
            $order = Order::with('lines')->lockForUpdate()->findOrFail($id);
            Gate::authorize('update', $order);
            $key = hash('sha256', $snapshot);
            if ($order->shipment?->pack_key === $key && $order->shipment->stage === 'packed' && $order->status === 'processing') {
                return $order->shipment;
            }
            self::check($order, $snapshot);
            if (($reason = self::blocked($order)) || $order->shipment) {
                self::invalid($reason ?? 'This parcel is already packed.');
            }
            if (count($quantities) !== $order->lines->count()) {
                self::invalid('Confirm the full quantity for every order line.');
            }
            foreach ($order->lines as $line) {
                if ((int) ($quantities[$line->id] ?? 0) !== (int) $line->quantity) {
                    self::invalid('Pack exactly '.$line->quantity.' units of '.$line->sku.'. Partial parcels are not supported.');
                }
            }
            if ($order->status === 'pending') {
                app(Fulfillment::class)->transition($id, 'confirmed', auth()->id());
            }
            if ($order->status !== 'processing') {
                app(Fulfillment::class)->transition($id, 'processing', auth()->id());
            }
            $shipment = Shipment::create(['order_id' => $id, 'stage' => 'packed', 'packed_at' => now(), 'pack_key' => $key]);
            $this->history($order->fresh(), 'Full order packed; stock remains reserved.');
            OrderMessages::record($order->fresh(), 'packed-'.$shipment->id, 'Order packed', 'Your order is packed and ready for courier handover. We will share tracking details after dispatch.');

            return $shipment;
        }, 5);
    }

    /** @param array<int, array{order_id: int, snapshot: string, courier_id: int, tracking_number: string}> $rows */
    public function handover(array $rows, string $key): DispatchManifest
    {
        Gate::authorize('manage-commerce');
        Validator::make(compact('rows', 'key'), ['key' => 'required|uuid', 'rows' => 'required|array|min:1|max:100', 'rows.*.order_id' => 'required|integer|distinct', 'rows.*.snapshot' => 'required|string', 'rows.*.courier_id' => 'required|integer', 'rows.*.tracking_number' => 'required|string|max:150'])->validate();
        $rows = collect($rows)->sortBy('order_id')->values()->all();
        $hash = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($rows, $key, $hash): DispatchManifest {
            DB::table('dispatch_manifests')->insertOrIgnore(['submission_key' => $key, 'payload_hash' => $hash, 'reference' => 'MAN-'.Str::ulid(), 'actor_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            $manifest = DispatchManifest::where('submission_key', $key)->lockForUpdate()->firstOrFail();
            if (! hash_equals($manifest->payload_hash, $hash)) {
                self::invalid('This handover key was already used for different parcels.');
            }
            if ($manifest->shipments()->exists()) {
                return $manifest;
            }
            foreach ($rows as $row) {
                $order = Order::lockForUpdate()->findOrFail($row['order_id']);
                Gate::authorize('update', $order);
                self::check($order, $row['snapshot']);
                if (($reason = self::blocked($order)) || $order->shipment?->stage !== 'packed') {
                    self::invalid($order->number.': '.($reason ?? 'Pack this order before handover.'));
                }
                $courier = Courier::lockForUpdate()->find($row['courier_id']);
                $tracking = trim($row['tracking_number']);
                if (! $courier?->is_active || $tracking === '' || preg_match('/[\x00-\x1f\x7f]/', $tracking)) {
                    self::invalid($order->number.': Select an active courier and a valid tracking number.');
                }
                if (Shipment::where('courier_id', $courier->id)->where('tracking_number', $tracking)->exists()) {
                    self::invalid($order->number.': Tracking number '.$tracking.' is already assigned to this courier.');
                }
                $order->shipment->update(['courier_id' => $courier->id, 'courier_name' => $courier->name, 'service_name' => $courier->service_name, 'tracking_template' => $courier->tracking_template, 'tracking_number' => $tracking, 'expected_cod_minor' => self::collectible($order), 'stage' => 'with_courier', 'dispatched_at' => now(), 'dispatch_manifest_id' => $manifest->id, 'version' => $order->shipment->version + 1]);
                app(Fulfillment::class)->transition($order->id, 'shipped', auth()->id());
            }

            return $manifest;
        }, 5);
    }

    public function event(int $id, string $snapshot, string $target, string $reason, ?string $note = null, ?string $explanation = null): void
    {
        Gate::authorize('manage-commerce');
        Validator::make(compact('target', 'reason', 'note', 'explanation'), ['target' => 'required|in:issue,with_courier,returning,delivered', 'reason' => 'required|string|max:150', 'note' => 'nullable|string|max:1000', 'explanation' => 'nullable|string|max:1000'])->validate();
        DB::transaction(function () use ($id, $snapshot, $target, $reason, $note, $explanation): void {
            $order = Order::lockForUpdate()->findOrFail($id);
            Gate::authorize('update', $order);
            self::check($order, $snapshot);
            $shipment = $order->shipment;
            $next = ['with_courier' => ['issue', 'returning', 'delivered'], 'issue' => ['with_courier', 'returning', 'delivered']];
            if ($order->status !== 'shipped' || ! in_array($target, $next[$shipment?->stage] ?? [], true)) {
                self::invalid('This parcel cannot make that transition. Delivered purchases use the customer return workflow.');
            }
            if ($order->requests()->where('type', 'return')->whereIn('status', ['pending', 'approved', 'received'])->exists()) {
                self::invalid('This order is already in the customer return workflow. Do not receive it twice.');
            }
            if ($target === 'delivered') {
                app(Fulfillment::class)->transition($id, 'delivered', auth()->id());

                return;
            }
            $shipment->update(['stage' => $target, 'version' => $shipment->version + 1]);
            $history = $this->history($order, 'Courier '.$target.': '.$reason.'. '.($note ?? ''));
            $public = match ($target) {
                'issue' => 'A delivery attempt needs attention.', 'returning' => 'The parcel is returning to the store.', default => 'Another delivery attempt is planned.'
            };
            OrderMessages::record($order, 'courier-event-'.$history, 'Delivery update', $public.' '.($explanation ?? ''));
        }, 5);
    }

    public function receive(int $id, string $snapshot, array $lines, ?string $note = null): void
    {
        Gate::authorize('manage-commerce');
        Validator::make(compact('lines', 'note'), ['lines' => 'required|array', 'lines.*.resellable' => 'required|integer|min:0|max:100', 'lines.*.damaged' => 'required|integer|min:0|max:100', 'note' => 'nullable|string|max:1000'])->validate();
        DB::transaction(function () use ($id, $snapshot, $lines, $note): void {
            $order = Order::with('lines')->lockForUpdate()->findOrFail($id);
            Gate::authorize('update', $order);
            $shipment = $order->shipment;
            if ($shipment?->received_at) {
                return;
            }
            self::check($order, $snapshot);
            if ($shipment?->stage !== 'returning' || $order->status !== 'shipped') {
                self::invalid('Only a returning courier parcel can be physically received. Delivered purchases use customer returns.');
            }
            if ($order->requests()->where('type', 'return')->whereIn('status', ['pending', 'approved', 'received'])->exists()) {
                self::invalid('A customer return already exists. Do not use both return workflows for this parcel.');
            }
            if (count($lines) !== $order->lines->count()) {
                self::invalid('Classify every parcel line.');
            }
            $variants = ProductVariant::whereIn('id', $order->lines->pluck('product_variant_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($order->lines as $line) {
                $counts = $lines[$line->id] ?? [];
                if ((int) ($counts['resellable'] ?? -1) + (int) ($counts['damaged'] ?? -1) !== (int) $line->quantity) {
                    self::invalid($line->sku.': Resellable plus damaged must equal '.$line->quantity.'.');
                }
                DB::table('courier_return_receipts')->insert(['shipment_id' => $shipment->id, 'order_line_id' => $line->id, 'resellable' => $counts['resellable'], 'damaged' => $counts['damaged'], 'actor_id' => auth()->id(), 'note' => $note, 'created_at' => now()]);
                if ((int) $counts['resellable'] > 0) {
                    if (! isset($variants[$line->product_variant_id])) {
                        self::invalid($line->sku.': Restore the archived SKU before receiving resellable stock.');
                    }
                    app(Inventory::class)->change($variants[$line->product_variant_id], (int) $counts['resellable'], 0, 'Courier return receipt #'.$shipment->id, $id, auth()->id());
                }
            }
            $shipment->update(['stage' => 'returned', 'received_at' => now(), 'version' => $shipment->version + 1]);
            $order->update(['status' => 'returned']);
            $this->history($order, 'Courier parcel received and classified. '.($note ?? ''));
            OrderMessages::record($order, 'courier-received-'.$shipment->id, 'Parcel returned to store', 'Your parcel has arrived back at the store. Contact us about next steps. Any eligible refund is handled separately.');
        }, 5);
    }

    public function attachLegacy(int $id, string $snapshot, int $courierId, string $tracking): void
    {
        Gate::authorize('manage-commerce');
        DB::transaction(function () use ($id, $snapshot, $courierId, $tracking): void {
            $order = Order::lockForUpdate()->findOrFail($id);
            self::check($order, $snapshot);
            if ($order->shipment || ! in_array($order->status, ['shipped', 'delivered'], true)) {
                self::invalid('Only an older shipped or delivered order without courier data can be linked.');
            }
            $courier = Courier::where('is_active', true)->findOrFail($courierId);
            Validator::make(['tracking' => $tracking], ['tracking' => 'required|string|max:150'])->validate();
            if (Shipment::where('courier_id', $courierId)->where('tracking_number', trim($tracking))->exists()) {
                self::invalid('That courier tracking number is already assigned.');
            }
            Shipment::create(['order_id' => $id, 'courier_id' => $courierId, 'courier_name' => $courier->name, 'service_name' => $courier->service_name, 'tracking_template' => $courier->tracking_template, 'tracking_number' => trim($tracking), 'stage' => $order->status === 'delivered' ? 'delivered' : 'with_courier', 'expected_cod_minor' => $order->payment_method === 'cod' ? $order->total_minor : 0, 'collected_minor' => $order->payment_method === 'cod' ? $order->collected_minor : 0, 'collection_reference' => $order->payment_status === 'paid' ? 'Legacy verified payment: see order history' : null, 'collection_confirmed_at' => $order->payment_status === 'paid' ? now() : null]);
            $this->history($order, 'Legacy courier details attached; existing stock and payment history retained. No dispatch repeated.');
        });
    }

    private function history(Order $order, string $note): int
    {
        return $order->histories()->create(['actor_id' => auth()->id(), 'status' => $order->status, 'payment_status' => $order->payment_status, 'note' => mb_substr($note, 0, 500)])->id;
    }

    private static function invalid(string $message): never
    {
        throw ValidationException::withMessages(['dispatch' => $message]);
    }
}
