<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class PurchaseReceiving
{
    public const COUNTS = ['accepted', 'damaged_replace', 'damaged_short', 'short_closed'];

    public static function baseline(PurchaseOrder $order): string
    {
        return Crypt::encryptString(json_encode(['id' => $order->id, 'version' => $order->fresh()->version], JSON_THROW_ON_ERROR));
    }

    private function normalize(array $rows, bool $correction): array
    {
        $result = [];
        foreach ($rows as $id => $row) {
            $counts = [];
            foreach (self::COUNTS as $field) {
                $value = $row[$field] ?? 0;
                if ($value === '' || $value === null) {
                    $value = 0;
                }
                if (filter_var($value, FILTER_VALIDATE_INT) === false || abs((int) $value) > 100000 || (! $correction && (int) $value < 0)) {
                    Purchasing::invalid('Enter whole quantities; negative adjustments are only allowed when correcting a receipt.');
                }
                $counts[$field] = (int) $value;
            }
            if (array_sum(array_map('abs', $counts)) === 0) {
                continue;
            }
            $result[(int) $id] = $counts + ['unit_cost_minor' => Pricing::parse($row['cost'] ?? null, 'PKR')];
        }
        if (! $result) {
            Purchasing::invalid('Enter quantities for at least one SKU. Blank rows are skipped.');
        }
        ksort($result);

        return $result;
    }

    public function preview(int $id, string $baseline, array $rows, ?int $correctionOf = null): array
    {
        Purchasing::authorize();
        $normalized = $this->normalize($rows, $correctionOf !== null);
        $expected = json_decode(Crypt::decryptString($baseline), true, flags: JSON_THROW_ON_ERROR);
        $order = PurchaseOrder::with('lines')->findOrFail($id);
        if ($expected['id'] !== $id || $expected['version'] !== $order->version) {
            Purchasing::invalid('Another receipt or change was recorded. Your inputs are retained; review the latest order before resubmitting.');
        }
        $this->check($order, $normalized, $correctionOf);

        return ['rows' => $normalized, 'token' => Crypt::encryptString(json_encode(['id' => $id, 'baseline' => $expected, 'rows' => $normalized, 'correction' => $correctionOf], JSON_THROW_ON_ERROR))];
    }

    public function post(int $id, string $key, string $token, array $rows, ?string $deliveryReference = null, ?string $reason = null, ?int $correctionOf = null): PurchaseReceipt
    {
        Purchasing::authorize();
        Validator::make(['key' => $key, 'delivery_reference' => $deliveryReference, 'reason' => $reason], ['key' => 'required|uuid', 'delivery_reference' => 'nullable|string|max:255', 'reason' => ($correctionOf ? 'required' : 'nullable').'|string|max:2000'])->validate();
        $normalized = $this->normalize($rows, $correctionOf !== null);
        try {
            $plan = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            Purchasing::invalid('Preview the receipt before posting.');
        }
        if ($plan['id'] !== $id || $plan['rows'] !== $normalized || $plan['correction'] !== $correctionOf) {
            Purchasing::invalid('Quantities or costs changed. Preview again before posting.');
        }
        $hash = hash('sha256', json_encode([$id, $normalized, $deliveryReference, $reason, $correctionOf], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($id, $key, $plan, $normalized, $hash, $deliveryReference, $reason, $correctionOf): PurchaseReceipt {
            $order = PurchaseOrder::with('lines')->lockForUpdate()->findOrFail($id);
            $existing = PurchaseReceipt::where('submission_key', $key)->first();
            if ($existing) {
                if ($existing->payload_hash !== $hash) {
                    Purchasing::invalid('This receipt key was already used for different quantities.');
                }

return $existing;
            }
            if ($order->version !== $plan['baseline']['version']) {
                Purchasing::invalid('Another receipt was posted after this screen opened. No stock was changed; your entries are retained. Reopen to review the latest balances.');
            }
            $this->check($order, $normalized, $correctionOf);
            $onlyShort = collect($normalized)->every(fn ($r) => $r['accepted'] === 0 && $r['damaged_replace'] === 0 && $r['damaged_short'] === 0);
            if ($onlyShort && ! filled($reason)) {
                Purchasing::invalid('Give a reason for closing a shortfall.');
            }
            $receipt = PurchaseReceipt::create(['reference' => ($correctionOf ? 'ADJ-' : 'RCV-').Str::ulid(), 'purchase_order_id' => $id, 'submission_key' => $key, 'payload_hash' => $hash, 'kind' => $correctionOf ? 'correction' : ($onlyShort ? 'short_close' : 'delivery'), 'correction_of_id' => $correctionOf, 'delivery_reference' => $deliveryReference, 'reason' => $reason, 'actor_id' => auth()->id()]);
            $variants = ProductVariant::whereIn('id', $order->lines->whereIn('id', array_keys($normalized))->pluck('product_variant_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($normalized as $lineId => $counts) {
                $line = $order->lines->firstWhere('id', $lineId);
                $entry = $receipt->lines()->create(['purchase_order_line_id' => $lineId] + $counts);
                if ($counts['accepted'] !== 0) {
                    app(Inventory::class)->change($variants[$line->product_variant_id], $counts['accepted'], 0, 'Purchase '.$receipt->reference.' / '.$line->sku, null, auth()->id(), $entry->id);
                }
                foreach (self::COUNTS as $field) {
                    $line->$field += $counts[$field];
                }
                $line->save();
            }
            $remaining = $order->lines->sum(fn ($l) => $l->outstanding);
            $short = $order->lines->sum(fn ($l) => $l->damaged_short + $l->short_closed);
            $arrived = $order->lines->sum(fn ($l) => $l->accepted + $l->damaged_replace + $l->damaged_short);
            $order->update(['status' => $remaining > 0 ? ($arrived > 0 ? 'partially_received' : 'ordered') : ($short > 0 ? 'short_closed' : 'completed'), 'version' => $order->version + 1]);

            return $receipt;
        }, 5);
    }

    private function check(PurchaseOrder $order, array $rows, ?int $correctionOf): void
    {
        if (! $order->issued_at || $order->status === 'cancelled' || (! $correctionOf && ! in_array($order->status, PurchaseOrder::OPEN, true))) {
            Purchasing::invalid('Only an issued, open purchase order can receive a delivery.');
        }
        $original = null;
        if ($correctionOf) {
            $original = $order->receipts()->whereNull('correction_of_id')->with('lines')->findOrFail($correctionOf);
        }
        foreach ($rows as $id => $counts) {
            $line = $order->lines->firstWhere('id', $id);
            if (! $line) {
                Purchasing::invalid('A receipt line does not belong to this purchase order.');
            }
            if (! $correctionOf && array_sum(array_intersect_key($counts, array_flip(self::COUNTS))) > $line->outstanding) {
                Purchasing::invalid($line->sku.': Carton quantities exceed the '.$line->outstanding.' units still expected.');
            }
            if ($correctionOf) {
                $originalLine = $original->lines->firstWhere('purchase_order_line_id', $id);
                if (! $originalLine) {
                    Purchasing::invalid($line->sku.': Choose a line from the original receipt.');
                }
                foreach (self::COUNTS as $field) {
                    $adjusted = (int) DB::table('purchase_receipt_lines as l')->join('purchase_receipts as r', 'r.id', '=', 'l.purchase_receipt_id')->where('r.correction_of_id', $correctionOf)->where('l.purchase_order_line_id', $id)->sum('l.'.$field);
                    if ($originalLine->$field + $adjusted + $counts[$field] < 0) {
                        Purchasing::invalid($line->sku.': Correction would remove more '.$field.' units than this receipt recorded.');
                    }
                }
            }
            foreach (self::COUNTS as $field) {
                if ($line->$field + $counts[$field] < 0) {
                    Purchasing::invalid($line->sku.': A corrected balance cannot be negative.');
                }
            }
            if ($line->accepted + $line->damaged_short + $line->short_closed + $counts['accepted'] + $counts['damaged_short'] + $counts['short_closed'] > $line->quantity) {
                Purchasing::invalid($line->sku.': Correction would over-receive the order.');
            }
        }
    }
}
