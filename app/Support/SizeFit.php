<?php

namespace App\Support;

final class SizeFit
{
    public static function method(array $measurement, ?string $basis): string
    {
        return match ($measurement['method'] ?? null) {
            'flat_width' => $basis === 'garment' ? 'Width - jacket laid flat' : 'Flat width (not circumference)',
            'circumference' => $basis === 'body' ? 'Circumference - around the body' : ($basis === 'garment' ? 'Circumference - around the jacket' : 'Full circumference'),
            'length' => 'Length',
            default => '',
        };
    }

    public static function instruction(array $measurement, ?string $basis): ?string
    {
        if (filled($measurement['instruction'] ?? null)) {
            return $measurement['instruction'];
        }
        if (strtolower(trim($measurement['label'] ?? '')) !== 'chest') {
            return null;
        }
        if ($basis === 'garment' && ($measurement['method'] ?? null) === 'flat_width') {
            return 'Lay a jacket that fits you flat, zip it up, and measure from armpit to armpit.';
        }
        if ($basis === 'body' && ($measurement['method'] ?? null) === 'circumference') {
            return 'Wrap the tape around the fullest part of your chest, keeping it level and comfortably snug.';
        }

        return null;
    }
}
