<?php

namespace App\Services;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class VariantPricing
{
    public static function authorize(Product $product): void
    {
        Gate::authorize('manage-commerce');
        abort_unless(ProductResource::canEdit($product) && ! $product->trashed(), 403);
    }

    /** @return array<string, mixed> */
    public function preview(Product $product, array $ids, string $currency, string $mode, ?string $amount): array
    {
        self::authorize($product);
        if (! in_array($currency, ['PKR', 'USD'], true) || ! in_array($mode, ['missing', 'replace'], true)) {
            throw ValidationException::withMessages(['prices' => 'Choose a currency and update mode.']);
        }
        $minor = Pricing::parse($amount, $currency);
        if ($minor === null || $minor <= 0) {
            throw ValidationException::withMessages(['prices' => 'Enter a positive price; blank never clears a price.']);
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        $variants = ProductVariant::whereIn('id', $ids)->whereHas('productColor', fn ($query) => $query->where('product_id', $product->id))->orderBy('id')->get();
        if (! $ids || $variants->count() !== count($ids)) {
            throw ValidationException::withMessages(['prices' => 'Select saved variants belonging to this product.']);
        }
        $product = $product->fresh();
        $field = strtolower($currency).'_price_minor';
        $rows = $variants->map(function (ProductVariant $variant) use ($field, $product, $currency, $mode, $minor): array {
            $current = Pricing::resolve($variant, $currency, $product);
            $change = ($mode === 'replace' || $current === null) && $current !== $minor;

            return ['id' => $variant->id, 'color_id' => $variant->product_color_id, 'sku' => $variant->sku,
                'raw' => $variant->$field === null ? null : (int) $variant->$field,
                'current' => $current, 'proposed' => $change ? $minor : $current, 'change' => $change];
        })->all();
        $plan = ['product' => $product->id, 'ids' => $ids, 'currency' => $currency, 'mode' => $mode, 'minor' => $minor,
            'base' => $product->$field === null ? null : (int) $product->$field, 'rows' => $rows];

        return $plan + ['token' => Crypt::encryptString(json_encode($plan, JSON_THROW_ON_ERROR))];
    }

    /** @return array<string, mixed> */
    public function apply(Product $product, array $ids, string $currency, string $mode, ?string $amount, string $token): array
    {
        self::authorize($product);
        if ($token === '') {
            throw ValidationException::withMessages(['prices' => 'Click Preview prices before confirming.']);
        }
        $expected = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);

        return DB::transaction(function () use ($product, $ids, $currency, $mode, $amount, $expected): array {
            $locked = ProductVariant::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $current = $this->preview($product, $ids, $currency, $mode, $amount);
            unset($current['token']);
            if ($current !== $expected) {
                $conflicts = collect($current['rows'])->filter(fn ($row) => $row !== collect($expected['rows'] ?? [])->firstWhere('id', $row['id']))->pluck('sku')->implode(', ');
                throw ValidationException::withMessages(['prices' => 'Prices or selection changed'.($conflicts ? ' for '.$conflicts : '').'. Nothing was saved. Click Preview prices again to review current values, then confirm.']);
            }
            $field = strtolower($currency).'_price_minor';
            foreach ($current['rows'] as $row) {
                if ($row['change']) {
                    $locked[$row['id']]->update([$field => $row['proposed']]);
                }
            }

            return $current;
        }, 3);
    }
}
