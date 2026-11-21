<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class Replenishment
{
    public static function query(bool $needed = true): Builder
    {
        $incoming = DB::table('purchase_order_lines as l')->join('purchase_orders as p', 'p.id', '=', 'l.purchase_order_id')->whereIn('p.status', PurchaseOrder::OPEN)->selectRaw('l.product_variant_id, SUM(l.quantity-l.accepted-l.damaged_short-l.short_closed) incoming_units')->groupBy('l.product_variant_id');
        $source = DB::table('supplier_sources as ss')->join('suppliers as sp', 'sp.id', '=', 'ss.supplier_id')->where('ss.is_preferred', true)->where('sp.is_active', true)->select('ss.*', 'sp.name as supplier_name');
        $base = ProductVariant::query()->where('product_variants.is_active', true)->whereHas('productColor.product', fn ($q) => $q->where('is_active', true))->leftJoinSub($incoming, 'incoming', 'incoming.product_variant_id', '=', 'product_variants.id')->leftJoinSub($source, 'preferred', 'preferred.product_variant_id', '=', 'product_variants.id')
            ->select('product_variants.*', 'preferred.supplier_id', 'preferred.supplier_name', 'preferred.quoted_cost_minor')->selectRaw(Inventory::availableSql('product_variants').' available_now, COALESCE(incoming.incoming_units,0) incoming_units, COALESCE(preferred.pack_size,1) source_pack, COALESCE(preferred.minimum_quantity,1) source_minimum')
            ->selectSub(DB::table('purchase_order_lines as dl')->join('purchase_orders as dp', 'dp.id', '=', 'dl.purchase_order_id')->whereColumn('dl.product_variant_id', 'product_variants.id')->where('dp.status', 'draft')->selectRaw('GROUP_CONCAT(dp.reference)'), 'existing_drafts');
        $query = ProductVariant::query()->fromSub($base->toBase(), 'product_variants')->with('productColor.product');
        if ($needed) {
            $query->whereNotNull('reorder_point')->whereNotNull('reorder_target')->whereRaw('available_now + incoming_units <= reorder_point');
        }

        return $query;
    }

    public static function suggestion(ProductVariant $variant): int
    {
        $gap = max(0, (int) $variant->reorder_target - (int) $variant->available_now - (int) $variant->incoming_units);
        if ($gap === 0) {
            return 0;
        }
        $pack = max(1, (int) $variant->source_pack);

        return (int) (ceil(max($gap, (int) $variant->source_minimum) / $pack) * $pack);
    }

    public static function explanation(ProductVariant $v): string
    {
        return $v->available_now.' available + '.$v->incoming_units.' incoming = '.($v->available_now + $v->incoming_units).' projected; target '.($v->reorder_target ?? 'not configured').', packs of '.$v->source_pack.', minimum '.$v->source_minimum.'; suggest '.self::suggestion($v).'.';
    }

    public function settings(array $ids, ?int $point, ?int $target, bool $keep = false, ?int $productId = null): int
    {
        Purchasing::authorize();
        Validator::make(compact('ids', 'point', 'target'), ['ids' => 'required|array|min:1|max:200', 'ids.*' => 'integer|distinct', 'point' => 'nullable|integer|min:0|max:100000', 'target' => 'nullable|integer|min:0|max:100000'])->validate();
        if (($point === null) !== ($target === null) || ($point !== null && $target < $point)) {
            Purchasing::invalid('Set both point and target, with target at least the point, or clear both. Zero is a configured setting.');
        }

        return DB::transaction(function () use ($ids, $point, $target, $keep, $productId): int {
            $variants = ProductVariant::whereIn('id', $ids)->when($productId, fn ($q) => $q->whereHas('productColor', fn ($c) => $c->where('product_id', $productId)))->orderBy('id')->lockForUpdate()->get();
            if ($variants->count() !== count($ids)) {
                Purchasing::invalid('Select saved variants from this product.');
            }
            $count = 0;
            foreach ($variants as $v) {
                if ($keep && $v->reorder_point !== null) {
                    continue;
                } $v->update(['reorder_point' => $point, 'reorder_target' => $target]);
                $count++;
            }

            return $count;
        }, 3);
    }

    public function preview(array $ids): array
    {
        Purchasing::authorize();
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        $variants = self::query()->whereIn('id', $ids)->orderBy('id')->get();
        if (! $ids || count($ids) > 200 || $variants->count() !== count($ids)) {
            Purchasing::invalid('A selected SKU no longer needs reorder. Refresh the list.');
        }
        $rows = $variants->map(fn ($v): array => ['variant_id' => $v->id, 'sku' => $v->sku, 'supplier_id' => $v->supplier_id === null ? null : (int) $v->supplier_id, 'supplier_name' => $v->supplier_name, 'quantity' => self::suggestion($v), 'cost' => $v->quoted_cost_minor === null ? null : (int) $v->quoted_cost_minor, 'drafts' => $v->existing_drafts, 'calculation' => self::explanation($v)])->all();

        return ['rows' => $rows, 'token' => Crypt::encryptString(json_encode(['ids' => $ids, 'rows' => $rows], JSON_THROW_ON_ERROR))];
    }

    public function createDrafts(array $ids, string $key, string $token): array
    {
        Purchasing::authorize();
        Validator::make(compact('key'), ['key' => 'required|uuid'])->validate();
        try {
            $expected = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            Purchasing::invalid('Preview supplier grouping first.');
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        if ($ids !== $expected['ids']) {
            Purchasing::invalid('Selection changed. Preview again.');
        }
        $hash = hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($ids, $key, $hash, $expected): array {
            DB::table('purchase_draft_batches')->insertOrIgnore(['key' => $key, 'payload_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            $batch = DB::table('purchase_draft_batches')->where('key', $key)->lockForUpdate()->first();
            if ($batch->payload_hash !== $hash) {
                Purchasing::invalid('This action was already used with different suggestions.');
            }
            if ($batch->order_ids) {
                return json_decode($batch->order_ids, true, flags: JSON_THROW_ON_ERROR);
            }
            ProductVariant::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $plan = $this->preview($ids);
            if ($plan['rows'] !== $expected['rows']) {
                Purchasing::invalid('Availability, incoming, quotes or existing drafts changed. Preview again.');
            }
            $orderIds = [];
            foreach (collect($plan['rows'])->groupBy('supplier_id') as $supplier => $rows) {
                if (! $supplier || $rows->contains(fn ($r) => $r['quantity'] <= 0)) {
                    Purchasing::invalid('Assign an active preferred supplier and a positive target gap for every selected SKU.');
                }
                $order = app(Purchasing::class)->save(['supplier_id' => (int) $supplier, 'notes' => 'Created from reviewed replenishment suggestions.', 'lines' => $rows->map(fn ($r) => ['variant_id' => $r['variant_id'], 'quantity' => $r['quantity'], 'cost' => Pricing::decimal($r['cost'])])->all()]);
                $orderIds[] = $order->id;
            }
            DB::table('purchase_draft_batches')->where('key',$key)->update(['order_ids' => json_encode($orderIds), 'updated_at' => now()]);

            return $orderIds;
        }, 5);
    }
}
