<?php

namespace App\Services;

use App\Models\Product;
use App\Payments\Gateways;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CommerceSettings
{
    public static function defaults(): array
    {
        return ['international_shipping_mode' => 'quote', 'international_shipping_rates' => [], 'shipping_name' => 'Pakistan delivery', 'shipping_minor' => null, 'free_shipping_minor' => null, 'tax_basis_points' => null, 'cod_enabled' => false, 'bank_enabled' => false, 'bank_instructions' => '', 'expiry_hours' => 48, 'returns_enabled' => false, 'return_days' => 14, 'contact_email' => '', 'delivery_information' => '', 'returns_information' => '', 'returns_policy_approved' => false];
    }

    public static function get(): array
    {
        $stored = DB::table('store_settings')->where('key', 'commerce')->value('value');

        return array_replace(self::defaults(), $stored ? json_decode($stored, true, 512, JSON_THROW_ON_ERROR) : []);
    }

    public static function save(array $values): void
    {
        request()->attributes->remove('checkout_ready');
        request()->attributes->remove('checkout_payment_ready');
        DB::table('store_settings')->upsert([['key' => 'commerce', 'value' => json_encode(array_replace(self::defaults(), $values), JSON_THROW_ON_ERROR)]], ['key'], ['value']);
    }

    public static function checkoutReady(?Product $product = null): bool
    {
        if (! request()->attributes->has('checkout_ready')) {
            $settings = self::get();
            $paymentReady = self::paymentsReady($settings);
            request()->attributes->set('checkout_payment_ready', $paymentReady);
            $ready = $settings['shipping_minor'] !== null && $paymentReady;
            request()->attributes->set('checkout_ready', $ready);
        }

        return (bool) request()->attributes->get('checkout_ready') || ($product?->delivery_charge_minor !== null && request()->attributes->get('checkout_payment_ready'));
    }

    public static function paymentsReady(array $settings): bool
    {
        return (bool) array_filter(Gateways::all(), fn ($method) => $method->enabled($settings) && (! config('store.pakistan_only') || $method->code() === 'cod'));
    }

    public static function forQuote(array $quote, array $settings): array
    {
        $charges = [];
        foreach ($quote['lines'] as $line) {
            $charge = $line['product']?->delivery_charge_minor ?? $settings['shipping_minor'];
            if ($charge === null) {
                throw ValidationException::withMessages(['checkout' => 'Delivery is not configured for one of the selected products. Please contact the store.']);
            }
            $charges[] = (int) $charge;
        }
        if ($charges) {
            $settings['shipping_minor'] = max($charges);
        }

        return $settings;
    }

    public static function quoteReady(array $quote): bool
    {
        try {
            $settings = self::forQuote($quote, self::get());

            return $settings['shipping_minor'] !== null && self::paymentsReady($settings);
        } catch (ValidationException) {
            return false;
        }
    }

    public static function totals(int $subtotal, ?array $settings = null): array
    {
        $s = $settings ?? self::get();
        if ($s['shipping_minor'] === null) {
            throw ValidationException::withMessages(['checkout' => 'Checkout is unavailable until delivery charges are configured.']);
        }
        $shipping = $s['free_shipping_minor'] !== null && $subtotal >= $s['free_shipping_minor'] ? 0 : (int) $s['shipping_minor'];
        $tax = 0;

        return ['subtotal_minor' => $subtotal, 'discount_minor' => 0, 'tax_minor' => $tax, 'shipping_minor' => $shipping, 'total_minor' => $subtotal + $shipping + $tax, 'tax_basis_points' => 0, 'shipping_name' => $s['shipping_name'], 'currency' => 'PKR'];
    }

    public static function productDeliveryDescription(Product $product, string $currency): string
    {
        $settings = self::get();
        if ($currency === 'USD') {
            return match ($settings['international_shipping_mode']) {
                'included' => 'Shipping included in the international selling price.',
                'separate' => 'Shipping is charged separately by destination. Enter your country in the inquiry to see a configured estimate; otherwise request a shipping quote.',
                default => 'Request a shipping quote for your destination. No payment is collected online.',
            };
        }
        $charge = $product->delivery_charge_minor ?? $settings['shipping_minor'];
        if ($charge === null) {
            return 'Contact the store for a delivery quote before ordering.';
        }
        $message = (int) $charge === 0 ? 'Free delivery' : 'Delivery '.Pricing::format((int) $charge);
        if ($settings['free_shipping_minor'] !== null && (int) $charge > 0) {
            $message .= ' · Free from '.Pricing::format((int) $settings['free_shipping_minor']);
        }

        return $message;
    }
}
