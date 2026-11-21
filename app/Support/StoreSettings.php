<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class StoreSettings
{
    public const WHATSAPP_NUMBER_PATTERN = '/^\+?[1-9][0-9]{7,14}$/D';

    public static function whatsappNumber(): ?string
    {
        $number = trim((string) DB::table('store_settings')->where('key', 'whatsapp_number')->value('value'));

        return preg_match(self::WHATSAPP_NUMBER_PATTERN, $number) ? ltrim($number, '+') : null;
    }

    public static function setWhatsappNumber(string $number): void
    {
        $number = trim($number);
        validator(['whatsappNumber' => $number], ['whatsappNumber' => ['nullable', 'regex:'.self::WHATSAPP_NUMBER_PATTERN]])->validate();
        DB::table('store_settings')->upsert([['key' => 'whatsapp_number', 'value' => ltrim($number, '+')]], ['key'], ['value']);
    }

    public static function brandName(): string
    {
        $attributes = request()->attributes;
        if (! $attributes->has('store_brand_name')) {
            $attributes->set('store_brand_name', DB::table('store_settings')->where('key', 'brand_name')->value('value') ?? config('store.brand_name'));
        }

        return $attributes->get('store_brand_name');
    }

    public static function setBrandName(string $name): void
    {
        DB::table('store_settings')->upsert(
            [['key' => 'brand_name', 'value' => $name]],
            ['key'],
            ['value'],
        );
        request()->attributes->remove('store_brand_name');
    }
}
