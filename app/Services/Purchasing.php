<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Purchasing
{
    public static function authorize(): void
    {
        Gate::authorize('manage-commerce');
    }

    public static function invalid(string $message): never
    {
        throw ValidationException::withMessages(['purchase' => $message]);
    }

    public static function variantLabel(ProductVariant $v): string
    {
        return ($v->productColor->product?->name ?? 'Archived product').' / '.$v->productColor->name.' / '.$v->size.' / '.$v->sku.(! $v->is_active || ! $v->productColor->product?->is_active ? ' [inactive]' : '');
    }

    public function source(array $data, ?SupplierSource $record = null): SupplierSource
    {
        self::authorize();
        Validator::make($data, ['supplier_id' => 'required|integer|exists:suppliers,id', 'product_variant_id' => 'required|integer|exists:product_variants,id', 'supplier_sku' => 'nullable|string|max:255', 'pack_size' => 'required|integer|min:1|max:100000', 'minimum_quantity' => 'required|integer|min:1|max:100000', 'lead_days' => 'nullable|integer|min:0|max:365', 'is_preferred' => 'boolean'])->validate();
        $cost = Pricing::parse($data['quote'] ?? null, 'PKR');

        return DB::transaction(function () use ($data, $record, $cost): SupplierSource {
            $variant = ProductVariant::lockForUpdate()->findOrFail($data['product_variant_id']);
            if ($record && ($record->product_variant_id !== $variant->id || $record->supplier_id !== (int) $data['supplier_id'])) {
                self::invalid('Create a new mapping to change supplier or store SKU.');
            }
            $record ??= SupplierSource::where('supplier_id', $data['supplier_id'])->where('product_variant_id', $variant->id)->first() ?? new SupplierSource;
            if ($data['is_preferred'] ?? false) {
                SupplierSource::where('product_variant_id', $variant->id)->where('id', '!=', $record->id ?? 0)->update(['is_preferred' => false]);
            }
            $record->fill(collect($data)->only(['supplier_id', 'product_variant_id', 'supplier_sku', 'pack_size', 'minimum_quantity', 'lead_days', 'is_preferred'])->all());
            if (! $record->exists || $record->quoted_cost_minor !== $cost) {
                $record->quoted_at = $cost === null ? null : now();
            }
            $record->quoted_cost_minor = $cost;
            $record->save();

            return $record;
        }, 3);
    }

    public function save(array $data, ?PurchaseOrder $record = null): PurchaseOrder
    {
        self::authorize();
        Validator::make($data, ['supplier_id' => 'required|integer|exists:suppliers,id', 'expected_at' => 'nullable|date_format:Y-m-d', 'supplier_reference' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:3000', 'lines' => 'required|array|min:1|max:200', 'lines.*.variant_id' => 'required|integer|distinct|exists:product_variants,id', 'lines.*.quantity' => 'required|integer|min:1|max:100000'])->validate();

        return DB::transaction(function () use ($data, $record): PurchaseOrder {
            if ($record) {
                $record = PurchaseOrder::lockForUpdate()->findOrFail($record->id);
                if ($record->status !== 'draft' || $record->version !== (int) ($data['version'] ?? 0)) {
                    self::invalid('This draft changed or was issued. Reopen it before editing.');
                }
            } else {
                $record = new PurchaseOrder(['reference' => 'PO-'.Str::ulid(), 'actor_id' => auth()->id(), 'version' => 0]);
            }
            $supplier = Supplier::findOrFail($data['supplier_id']);
            $record->fill(collect($data)->only(['supplier_id', 'expected_at', 'supplier_reference', 'notes'])->all());
            $record->fill(['supplier_name' => $supplier->name, 'freight_minor' => Pricing::parse($data['freight'] ?? null, 'PKR'), 'version' => $record->version + 1])->save();
            $record->lines()->delete();
            foreach ($data['lines'] as $row) {
                $v = ProductVariant::with(['productColor.product' => fn ($q) => $q->withTrashed()])->findOrFail($row['variant_id']);
                $source = SupplierSource::where('supplier_id', $supplier->id)->where('product_variant_id', $v->id)->first();
                if (! $source) {
                    self::invalid($v->sku.': Add a supplier-to-SKU mapping first.');
                }
                $record->lines()->create(['product_variant_id' => $v->id, 'product_name' => $v->productColor->product?->name ?? 'Archived product', 'color' => $v->productColor->name, 'size' => $v->size, 'sku' => $v->sku, 'supplier_sku' => $source->supplier_sku, 'quantity' => (int) $row['quantity'], 'unit_cost_minor' => Pricing::parse($row['cost'] ?? null, 'PKR')]);
            }

            return $record;
        }, 3);
    }

    public static function data(PurchaseOrder $order): array
    {
        return array_merge($order->only(['supplier_id', 'supplier_reference', 'notes', 'version']), ['expected_at' => $order->expected_at?->format('Y-m-d')]) + ['freight' => Pricing::decimal($order->freight_minor), 'lines' => $order->lines->map(fn ($l): array => ['variant_id' => $l->product_variant_id, 'quantity' => $l->quantity, 'cost' => Pricing::decimal($l->unit_cost_minor)])->all()];
    }

    public function issue(int $id, int $version): PurchaseOrder
    {
        self::authorize();

        return DB::transaction(function () use ($id, $version): PurchaseOrder {
            $order = PurchaseOrder::with(['lines.variant.productColor.product', 'supplier'])->lockForUpdate()->findOrFail($id);
            if ($order->issued_at) {
                return $order;
            }
            if ($order->status !== 'draft' || $order->version !== $version) {
                self::invalid('The draft changed. Review the current version before issuing.');
            }
            if (! $order->supplier->is_active) {
                self::invalid('Supplier is archived. Choose an available supplier before issue.');
            }
            if ($order->lines->isEmpty()) {
                self::invalid('Add at least one SKU.');
            }
            foreach ($order->lines as $line) {
                if ($line->unit_cost_minor === null) {
                    self::invalid($line->sku.': Enter a purchase cost. Unknown costs are not zero.');
                }
                if (! $line->variant->is_active || ! $line->variant->productColor->product?->is_active || $line->variant->productColor->product?->trashed()) {
                    self::invalid($line->sku.': This SKU/product is inactive or archived.');
                }
            }
            $order->update(['status' => 'ordered', 'issued_at' => now(), 'version' => $order->version + 1]);

            return $order;
        }, 3);
    }

    public function duplicate(PurchaseOrder $order): PurchaseOrder
    {
        self::authorize();
        $data = self::data($order->fresh(['lines']));
        $data['expected_at'] = null;
        $data['supplier_reference'] = null;
        $data['notes'] = 'Repeated purchase from '.$order->reference.'. Quotes refreshed; review costs and availability before issue.';
        foreach ($data['lines'] as &$line) {
            $source = SupplierSource::where('supplier_id', $order->supplier_id)->where('product_variant_id', $line['variant_id'])->first();
            $newCost = Pricing::decimal($source?->quoted_cost_minor);
            if ($line['cost'] !== $newCost) {
                $data['notes'] .= ' SKU '.$line['variant_id'].': previous '.$line['cost'].'; refreshed '.($newCost ?: 'unknown').'.';
            } $line['cost'] = $newCost;
        } unset($line);

        return $this->save($data);
    }

    public function cancel(int $id, int $version, string $reason): void
    {
        self::authorize();
        Validator::make(compact('reason'), ['reason' => 'required|string|max:255'])->validate();
        DB::transaction(function () use ($id, $version, $reason): void {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($id);
            if ($order->status === 'cancelled') {
                return;
            }
            if ($order->version !== $version || $order->receipts()->exists()) {
                self::invalid('The order changed or has receipts. Use Close remaining shortfall instead.');
            }
            $order->update(['status' => 'cancelled', 'closure_reason' => $reason, 'version' => $order->version + 1]);
        });
    }
}
