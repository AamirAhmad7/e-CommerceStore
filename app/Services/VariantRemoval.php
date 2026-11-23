<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VariantRemoval
{
    /** @return list<string> */
    public static function blockers(ProductVariant $variant): array
    {
        $reasons = [];
        if ((int) $variant->on_hand !== 0) {
            $reasons[] = 'on-hand stock';
        }
        if ((int) $variant->reserved !== 0) {
            $reasons[] = 'reserved stock';
        }
        foreach (['order_lines' => 'order history', 'stock_movements' => 'stock history', 'purchase_order_lines' => 'purchase orders', 'supplier_sources' => 'supplier references', 'restock_subscriptions' => 'restock requests'] as $table => $label) {
            if (DB::table($table)->where('product_variant_id', $variant->id)->exists()) {
                $reasons[] = $label;
            }
        }
        foreach (['assisted_sales', 'assisted_sale_links'] as $table) {
            if (DB::table($table)->whereNotNull('items->'.$variant->id)->exists()) {
                $reasons[] = 'assisted sales';
                break;
            }
        }

        return $reasons;
    }

    public static function requireUnused(ProductVariant $variant, string $field): void
    {
        $reasons = self::blockers($variant);
        if ($reasons) {
            throw ValidationException::withMessages([$field => 'Cannot delete '.$variant->sku.': '.implode(', ', $reasons).'. Edit this size and switch Variant active off to retain its history.']);
        }
    }
}
