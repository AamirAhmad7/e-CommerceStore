<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Brick\Money\Money;
use Illuminate\Validation\ValidationException;

final class Pricing
{
    public static function currency(?string $value): string
    {
        return in_array($value, ['USD', 'PKR'], true) ? $value : 'PKR';
    }

    public static function parse(?string $amount, string $currency): ?int
    {
        if ($amount === null || trim($amount) === '') {
            return null;
        }
        if (! in_array($currency, ['USD', 'PKR'], true) || ! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', trim($amount))) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount with at most two decimal places.']);
        }

        return Money::of(trim($amount), $currency)->getMinorAmount()->toInt();
    }

    public static function decimal(?int $minor, string $currency = 'PKR'): string
    {
        return $minor === null ? '' : (string) Money::ofMinor($minor, $currency)->getAmount();
    }

    public static function format(?int $minor, string $currency = 'PKR'): string
    {
        return $minor === null ? 'Price not configured' : $currency.' '.self::decimal($minor, $currency);
    }

    public static function resolve(ProductVariant $variant, string $currency, ?Product $product = null): ?int
    {
        $field = strtolower(self::currency($currency)).'_price_minor';
        $value = $variant->$field ?? ($product ?? $variant->productColor->product)?->$field;

        return $value === null ? null : (int) $value;
    }

    public static function range(Product $product, string $currency): string
    {
        $prices = $product->colors->flatMap(fn ($c) => $c->variants)->where('is_active', true)->map(fn ($v) => self::resolve($v, $currency, $product));
        $configured = $prices->filter(fn ($p) => $p !== null && $p > 0);
        if ($configured->isEmpty()) {
            return 'Price not configured';
        }

        return ($configured->unique()->count() > 1 || $configured->count() !== $prices->count() ? 'From ' : '').self::format($configured->min(), $currency);
    }
}
