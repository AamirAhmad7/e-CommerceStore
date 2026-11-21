<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Courier extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Courier $courier): void {
            if (! self::validTemplate($courier->tracking_template)) {
                throw ValidationException::withMessages(['tracking_template' => 'Use an HTTPS URL with one {tracking} placeholder in its path or query, no credentials or fragments.']);
            }
        });
    }

    public static function validTemplate(?string $template): bool
    {
        if (! filled($template)) {
            return true;
        }
        $url = str_replace('{tracking}', 'TRACKING', $template);
        $parts = parse_url($url);

        return substr_count($template, '{tracking}') === 1 && filter_var($url, FILTER_VALIDATE_URL) && ($parts['scheme'] ?? '') === 'https' && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['fragment']) && ! str_contains($parts['host'] ?? '', 'TRACKING') && ! preg_match('/[\x00-\x20]/', $template);
    }
}
