<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class OrderCare
{
    public static function canCancel(Order $order): bool
    {
        return in_array($order->status, ['pending', 'confirmed', 'processing'], true);
    }

    public static function canReturn(Order $order): bool
    {
        $settings = CommerceSettings::get();

        return $settings['returns_enabled'] && $order->status === 'delivered' && $order->delivered_at && now()->lte($order->delivered_at->copy()->addDays((int) $settings['return_days']));
    }

    public static function remaining(Order $order, int $lineId): int
    {
        $line = $order->lines->firstWhere('id', $lineId);
        $used = DB::table('order_request_lines as l')->join('order_requests as r', 'r.id', '=', 'l.order_request_id')->where('r.order_id', $order->id)->where('l.order_line_id', $lineId)->whereIn('r.status', ['pending', 'approved', 'received'])->sum('l.quantity');

        return max(0, ($line?->quantity ?? 0) - (int) $used);
    }

    public function request(int $orderId, string $type, string $key, string $reason, array $quantities = []): OrderRequest
    {
        Validator::make(compact('type', 'key', 'reason', 'quantities'), ['type' => 'required|in:cancellation,return', 'key' => 'required|uuid', 'reason' => 'required|string|max:1000', 'quantities.*' => 'required|integer|min:0|max:100'])->validate();

        return DB::transaction(function () use ($orderId, $type, $key, $reason, $quantities): OrderRequest {
            $order = Order::with('lines')->lockForUpdate()->findOrFail($orderId);
            $existing = $order->requests()->where('submission_key', $key)->first();
            $quantities = array_filter(array_map('intval', $quantities));
            ksort($quantities);
            if ($existing) {
                $original = $existing->lines()->pluck('quantity', 'order_line_id')->map(fn ($q) => (int) $q)->all();
                ksort($original);
                if ($existing->type !== $type || $existing->reason !== $reason || $original !== $quantities) {
                    $this->invalid('This submission was already used for different details. Reload the order.');
                }

                return $existing;
            }
            if ($type === 'cancellation') {
                if (! self::canCancel($order) || $order->requests()->where('type', 'cancellation')->where('status', 'pending')->exists()) {
                    $this->invalid('Cancellation is unavailable or already requested.');
                }
                if ($quantities) {
                    $this->invalid('Cancellation applies to the whole order.');
                }
            } else {
                if (in_array($order->shipment?->stage, ['returning', 'returned'], true)) {
                    $this->invalid('This parcel is in the courier return workflow. Do not process it as a customer return.');
                }
                if (! self::canReturn($order) || ! $quantities) {
                    $this->invalid('Returns are unavailable, outside the return window, or no quantity was selected.');
                }
                foreach ($quantities as $id => $quantity) {
                    if (! $order->lines->contains('id', $id) || $quantity > self::remaining($order, (int) $id)) {
                        $this->invalid('Return quantities exceed eligible purchased quantities. Pending, approved and received returns already count.');
                    }
                }
            }
            $request = $order->requests()->create(['submission_key' => $key, 'type' => $type, 'reason' => $reason]);
            foreach ($quantities as $id => $quantity) {
                $request->lines()->create(['order_line_id' => $id, 'quantity' => $quantity]);
            }
            OrderMessages::record($order, 'request-'.$request->id, 'Request received', 'Your '.$type.' request #'.$request->id.' is awaiting a decision. No refund or stock change has been made.');

            return $request;
        }, 5);
    }

    public function decide(int $orderId, int $requestId, bool $approve, int $actor, string $explanation, ?string $note = null): void
    {
        Validator::make(compact('explanation', 'note'), ['explanation' => 'required|string|max:1000', 'note' => 'nullable|string|max:1000'])->validate();
        DB::transaction(function () use ($orderId, $requestId, $approve, $actor, $explanation, $note): void {
            $order = Order::lockForUpdate()->findOrFail($orderId);
            $request = $order->requests()->lockForUpdate()->findOrFail($requestId);
            $target = $approve ? 'approved' : 'rejected';
            if ($request->status !== 'pending') {
                if ($request->status === $target || ($approve && $request->status === 'received')) {
                    return;
                }
                $this->invalid('Another staff member already decided this request. Refresh to see the decision.');
            }
            if ($approve && $request->type === 'cancellation') {
                if (! self::canCancel($order)) {
                    $this->invalid('This order has already shipped or closed. Reject the cancellation and explain the next step.');
                }
                app(Fulfillment::class)->transition($order->id, 'cancelled', $actor);
                $order->refresh();
            }
            if ($approve && $request->type === 'return' && $order->status !== 'delivered') {
                $this->invalid('Only delivered items can be returned.');
            }
            $request->update(['status' => $target, 'explanation' => $explanation, 'internal_note' => $note, 'decided_by' => $actor, 'decided_at' => now()]);
            OrderMessages::record($order, 'decision-'.$request->id, ucfirst($request->type).' '.$target, 'Your '.$request->type.' request #'.$request->id.' was '.$target.'. '.$explanation);
        }, 5);
    }

    /** Receive all approved units together, explicitly classifying every unit. */
    public function receive(int $orderId, int $requestId, array $dispositions, int $actor, ?string $note = null): void
    {
        Validator::make(['lines' => $dispositions, 'note' => $note], ['lines' => 'required|array', 'lines.*.resellable' => 'required|integer|min:0|max:100', 'lines.*.damaged' => 'required|integer|min:0|max:100', 'note' => 'nullable|string|max:1000'])->validate();
        DB::transaction(function () use ($orderId, $requestId, $dispositions, $actor, $note): void {
            $order = Order::lockForUpdate()->findOrFail($orderId);
            $request = $order->requests()->with('lines.orderLine')->lockForUpdate()->findOrFail($requestId);
            if ($request->status === 'received') {
                return;
            }
            if ($request->type !== 'return' || $request->status !== 'approved' || $order->status !== 'delivered') {
                $this->invalid('Only an approved return can be received.');
            }
            if (count($dispositions) !== $request->lines->count()) {
                $this->invalid('Classify every approved line.');
            }
            foreach ($request->lines as $line) {
                $d = $dispositions[$line->id] ?? null;
                if (! $d || (int) $d['resellable'] + (int) $d['damaged'] !== (int) $line->quantity) {
                    $this->invalid('Resellable plus damaged must equal the approved quantity for every line.');
                }
            }
            $variants = ProductVariant::whereIn('id', $request->lines->map(fn ($l) => $l->orderLine->product_variant_id))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($request->lines as $line) {
                $d = $dispositions[$line->id];
                DB::table('return_receipts')->insert(['order_request_line_id' => $line->id, 'resellable' => $d['resellable'], 'damaged' => $d['damaged'], 'actor_id' => $actor, 'note' => $note, 'created_at' => now()]);
                if ((int) $d['resellable'] > 0) {
                    app(Inventory::class)->change($variants[$line->orderLine->product_variant_id], (int) $d['resellable'], 0, 'Resellable return #'.$request->id, $order->id, $actor);
                }
            }
            $request->update(['status' => 'received', 'received_at' => now()]);
            OrderMessages::record($order, 'receipt-'.$request->id, 'Return received', 'We received the approved units for return #'.$request->id.'. Any refund is recorded separately after completion.');
        }, 5);
    }

    public function refund(int $orderId, int $amount, string $currency, string $reference, int $actor): void
    {
        $reference = trim($reference);
        Validator::make(compact('amount', 'currency', 'reference'), ['amount' => 'required|integer|min:1', 'currency' => 'required|in:PKR', 'reference' => 'required|string|max:150'])->validate();
        DB::transaction(function () use ($orderId, $amount, $currency, $reference, $actor): void {
            $order = Order::lockForUpdate()->findOrFail($orderId);
            $existing = DB::table('order_refunds')->where('order_id', $orderId)->where('reference', $reference)->first();
            if ($existing) {
                if ((int) $existing->amount_minor !== $amount || $existing->currency !== $currency) {
                    $this->invalid('That refund reference is already recorded with different details.');
                }

                return;
            }
            $refunded = (int) DB::table('order_refunds')->where('order_id', $orderId)->sum('amount_minor');
            if ($currency !== $order->currency || $amount > $order->collected_minor - $refunded) {
                $this->invalid('Refund exceeds funds actually collected minus previous refunds.');
            }
            if (! in_array($order->status, ['cancelled', 'returned'], true) && ! $order->requests()->where('type', 'return')->where('status', 'received')->exists()) {
                $this->invalid('Record a cancellation or receive an approved return before recording its refund.');
            }
            if ($order->promotion_snapshot && $amount > Promotions::refundLimit($order) - $refunded) {
                $this->invalid('Refund exceeds the amount paid for received return units, including allocated tax. Shipping is refundable only on a complete return or cancellation.');
            }
            $id = DB::table('order_refunds')->insertGetId(['order_id' => $orderId, 'amount_minor' => $amount, 'currency' => $currency, 'reference' => $reference, 'actor_id' => $actor, 'created_at' => now()]);
            OrderMessages::record($order, 'refund-'.$id, 'Refund recorded', 'The store recorded an externally completed refund of '.Pricing::format($amount, $currency).'. Contact the store if you cannot locate it.');
        }, 5);
    }

    public function rejectTransfer(int $orderId, int $actor, string $explanation, ?string $note = null): void
    {
        Validator::make(compact('explanation', 'note'), ['explanation' => 'required|string|max:1000', 'note' => 'nullable|string|max:1000'])->validate();
        DB::transaction(function () use ($orderId, $actor, $explanation, $note): void {
            $order = Order::lockForUpdate()->findOrFail($orderId);
            if ($order->payment_status === 'proof_rejected') {
                return;
            }
            if ($order->payment_method !== 'bank' || $order->payment_status !== 'proof_submitted' || ! in_array($order->status, ['pending', 'confirmed'], true)) {
                $this->invalid('This transfer is no longer awaiting review.');
            }
            $order->update(['payment_status' => 'proof_rejected']);
            $history = $order->histories()->create(['actor_id' => $actor, 'status' => $order->status, 'payment_status' => 'proof_rejected', 'note' => mb_substr('Transfer rejected. '.($note ?? ''), 0, 500)]);
            OrderMessages::record($order, 'transfer-rejected-'.$history->id, 'Transfer needs attention', $explanation.' You can submit a new proof from your order page while the reservation is open.');
        }, 5);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['order' => $message]);
    }
}
