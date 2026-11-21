<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CommerceSettings
{
    public static function defaults(): array
    {
        return ['shipping_name' => 'Pakistan delivery', 'shipping_minor' => null, 'free_shipping_minor' => null, 'tax_basis_points' => null, 'cod_enabled' => false, 'bank_enabled' => false, 'bank_instructions' => '', 'expiry_hours' => 48, 'returns_enabled' => false, 'return_days' => 14, 'contact_email' => '', 'delivery_information' => '', 'returns_information' => '', 'returns_policy_approved' => false];
    }

    public static function get(): array
    {
        $stored = DB::table('store_settings')->where('key', 'commerce')->value('value');

        return array_replace(self::defaults(), $stored ? json_decode($stored, true, 512, JSON_THROW_ON_ERROR) : []);
    }

    public static function save(array $values): void
    {
        DB::table('store_settings')->upsert([['key' => 'commerce', 'value' => json_encode(array_replace(self::defaults(), $values), JSON_THROW_ON_ERROR)]], ['key'], ['value']);
    }

    public static function totals(int $subtotal, ?array $settings = null): array
    {
        $s = $settings ?? self::get();
        if ($s['shipping_minor'] === null || $s['tax_basis_points'] === null) {
            throw ValidationException::withMessages(['checkout' => 'Checkout is unavailable until shipping and tax rules are configured.']);
        }
        $shipping = $s['free_shipping_minor'] !== null && $subtotal >= $s['free_shipping_minor'] ? 0 : (int) $s['shipping_minor'];
        $tax = intdiv($subtotal * (int) $s['tax_basis_points'] + 5000, 10000);

        return ['subtotal_minor' => $subtotal, 'discount_minor' => 0, 'tax_minor' => $tax, 'shipping_minor' => $shipping, 'total_minor' => $subtotal + $shipping + $tax, 'tax_basis_points' => (int) $s['tax_basis_points'], 'shipping_name' => $s['shipping_name'], 'currency' => 'PKR'];
    }
}
