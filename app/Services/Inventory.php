<?php

namespace App\Services;

use App\Jobs\SendRestockAlerts;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class Inventory
{
    public static function available(ProductVariant $variant): int
    {
        return $variant->on_hand === null ? 0 : max(0, (int) $variant->on_hand - (int) $variant->reserved);
    }

    public static function availableSql(string $alias = 'product_variants'): string
    {
        if (! preg_match('/^[a-z_]+$/', $alias)) {
            throw new \InvalidArgumentException('Invalid inventory alias.');
        }

        return "CASE WHEN $alias.on_hand IS NULL THEN 0 WHEN $alias.on_hand > $alias.reserved THEN $alias.on_hand - $alias.reserved ELSE 0 END";
    }

    public static function purchasable(ProductVariant $variant, string $currency = 'PKR', ?Product $product = null): bool
    {
        return $variant->is_active && self::available($variant) > 0 && Pricing::resolve($variant, 'PKR', $product) > 0 && Pricing::resolve($variant, $currency, $product) > 0;
    }

    public function adjustmentSnapshot(int $id): string
    {
        return DB::transaction(function () use ($id): string {
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($id);

            return Crypt::encryptString(json_encode([
                'id' => $id, 'sku' => $variant->sku, 'on_hand' => $variant->on_hand,
                'reserved' => $variant->reserved,
                'movement' => StockMovement::where('product_variant_id', $id)->latest('id')->lockForUpdate()->value('id'),
            ], JSON_THROW_ON_ERROR));
        });
    }

    /** @return array{id: int, sku: string, on_hand: ?int, reserved: int, movement: ?int} */
    public static function readSnapshot(string $snapshot): array
    {
        return json_decode(Crypt::decryptString($snapshot), true, flags: JSON_THROW_ON_ERROR);
    }

    public function adjustFromSnapshot(int $id, string $snapshot, string $mode, int $quantity, string $reason, ?int $actor): void
    {
        $expected = self::readSnapshot($snapshot);
        if ($expected['id'] !== $id || ! in_array($mode, ['opening', 'receive', 'count'], true) || $quantity < 0 || $quantity > 1000000 || trim($reason) === '') {
            throw ValidationException::withMessages(['stock' => 'Enter a valid quantity and reason.']);
        }
        DB::transaction(function () use ($id, $expected, $mode, $quantity, $reason, $actor): void {
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($id);
            $movement = StockMovement::where('product_variant_id', $id)->latest('id')->lockForUpdate()->value('id');
            if ($movement !== $expected['movement'] || $variant->on_hand !== $expected['on_hand'] || $variant->reserved !== $expected['reserved']) {
                throw ValidationException::withMessages(['stock' => 'Stock changed or this adjustment was already applied. Close and reopen Manage stock to review the latest balance. Your product edits are retained.']);
            }
            if (($mode === 'opening' && $variant->on_hand !== null) || ($mode !== 'opening' && $variant->on_hand === null)) {
                throw ValidationException::withMessages(['stock' => 'Use Opening stock only for an unconfigured size; otherwise receive stock or correct a count.']);
            }
            $delta = $mode === 'receive' ? $quantity : $quantity - (int) $variant->on_hand;
            $this->change($variant, $delta, 0, ucfirst($mode).': '.trim($reason), null, $actor);
        }, 3);
    }

    /** @param array<int, array{snapshot: string, quantity: mixed}> $rows */
    public function adjustBatch(Product $product, array $rows, string $mode, string $reason): int
    {
        VariantPricing::authorize($product);
        ksort($rows);

        return DB::transaction(function () use ($product, $rows, $mode, $reason): int {
            $units = 0;
            $changed = 0;
            foreach ($rows as $id => $row) {
                if (($row['quantity'] ?? '') === '' || $row['quantity'] === null) {
                    continue;
                }
                $variant = ProductVariant::whereHas('productColor', fn ($query) => $query->where('product_id', $product->id))->lockForUpdate()->findOrFail($id);
                if (filter_var($row['quantity'], FILTER_VALIDATE_INT) === false) {
                    throw ValidationException::withMessages(['stock' => $variant->sku.': Enter whole units.']);
                }
                try {
                    $this->adjustFromSnapshot((int) $id, $row['snapshot'], $mode, (int) $row['quantity'], $reason, auth()->id());
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['stock' => $variant->sku.': '.collect($exception->errors())->flatten()->implode(' ')]);
                }
                $units += $mode === 'receive' ? (int) $row['quantity'] : (int) $row['quantity'] - (int) $variant->on_hand;
                $changed++;
            }
            if (! $changed) {
                throw ValidationException::withMessages(['stock' => 'Enter a quantity for at least one size. Blank rows are skipped.']);
            }

            return $units;
        }, 3);
    }

    public function adjust(int $id, int $delta, string $reason, ?int $actor): void
    {
        if (trim($reason) === '' || abs($delta) > 1000000) {
            throw ValidationException::withMessages(['stock' => 'A reason and a valid adjustment are required.']);
        }
        DB::transaction(function () use ($id, $delta, $reason, $actor): void {
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($id);
            $this->change($variant, $delta, 0, $reason, null, $actor);
        }, 3);
    }

    /** Caller holds the variant row lock inside its transaction. */
    public function change(ProductVariant $variant, int $onHand, int $reserved, string $reason, ?int $order = null, ?int $actor = null, ?int $purchaseReceiptLine = null): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Inventory mutations require a transaction.');
        }
        $previousAvailable = self::available($variant);
        $nextHand = (int) $variant->on_hand + $onHand;
        $nextReserved = (int) $variant->reserved + $reserved;
        if ($nextHand < 0 || $nextReserved < 0 || $nextReserved > $nextHand) {
            throw ValidationException::withMessages(['stock' => 'Not enough available stock for '.$variant->sku.'.']);
        }
        $variant->forceFill(['on_hand' => $nextHand, 'reserved' => $nextReserved])->save();
        StockMovement::create(['product_variant_id' => $variant->id, 'order_id' => $order, 'actor_id' => $actor, 'on_hand_delta' => $onHand, 'reserved_delta' => $reserved, 'on_hand_after' => $nextHand, 'reserved_after' => $nextReserved, 'reason' => $reason] + ($purchaseReceiptLine === null ? [] : ['purchase_receipt_line_id' => $purchaseReceiptLine]));
        if (self::available($variant) > $previousAvailable) {
            DB::afterCommit(fn () => SendRestockAlerts::dispatch($variant->id));
        }
    }
}
