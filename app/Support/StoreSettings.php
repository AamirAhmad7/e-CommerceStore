<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class StoreSettings
{
    public const WHATSAPP_NUMBER_PATTERN = '/^\+?[1-9][0-9]{7,14}$/D';

    public static function whatsappNumber(): ?string
    {
        $number = trim((string) (self::publicValues()['whatsapp_number'] ?? ''));

        return preg_match(self::WHATSAPP_NUMBER_PATTERN, $number) ? ltrim($number, '+') : null;
    }

    public static function setWhatsappNumber(string $number): void
    {
        $number = trim($number);
        validator(['whatsappNumber' => $number], ['whatsappNumber' => ['nullable', 'regex:'.self::WHATSAPP_NUMBER_PATTERN]])->validate();
        DB::table('store_settings')->upsert([['key' => 'whatsapp_number', 'value' => ltrim($number, '+')]], ['key'], ['value']);
        self::forgetPublicValues();
    }

    public static function pages(): array
    {
        return array_replace(['about' => '', 'address' => '', 'hours' => '', 'privacy' => '', 'terms' => ''], json_decode(self::publicValues()['public_pages'] ?? '{}', true, 512, JSON_THROW_ON_ERROR));
    }

    public static function savePages(array $pages): void
    {
        $validated = validator($pages, ['about' => 'nullable|string|max:6000', 'address' => 'nullable|string|max:500', 'hours' => 'nullable|string|max:500', 'privacy' => 'nullable|string|max:12000', 'terms' => 'nullable|string|max:12000'])->validate();
        DB::table('store_settings')->upsert([['key' => 'public_pages', 'value' => json_encode($validated, JSON_THROW_ON_ERROR)]], ['key'], ['value']);
        self::forgetPublicValues();
    }

    public static function brandName(): string
    {
        return self::publicValues()['brand_name'] ?? config('store.brand_name');
    }

    /** @return array<string,string> */
    private static function publicValues(): array
    {
        $attributes = request()->attributes;
        $memoize = ! app()->runningInConsole() || app()->runningUnitTests();
        if ($memoize && $attributes->has('store_public_settings')) {
            return $attributes->get('store_public_settings');
        }
        $values = Cache::store(self::cacheStore())->remember(self::cacheKey(), 300, fn () => DB::table('store_settings')->whereIn('key', ['brand_name', 'whatsapp_number', 'public_pages'])->pluck('value', 'key')->all());
        if ($memoize) {
            $attributes->set('store_public_settings', $values);
        }

        return $values;
    }

    private static function cacheStore(): string
    {
        return app()->runningUnitTests() ? 'array' : config('store.public_cache_store', 'file');
    }

    private static function cacheKey(): string
    {
        return 'storefront-branding:'.hash('sha256', DB::connection()->getDatabaseName().'|'.config('app.url'));
    }

    private static function forgetPublicValues(): void
    {
        Cache::store(self::cacheStore())->forget(self::cacheKey());
        request()->attributes->remove('store_public_settings');
    }

    public static function setBrandName(string $name): void
    {
        DB::table('store_settings')->upsert(
            [['key' => 'brand_name', 'value' => $name]],
            ['key'],
            ['value'],
        );
        self::forgetPublicValues();
    }
}
