<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class ProductPhoto
{
    /** @return array<string, mixed> */
    public static function data(Media $media): array
    {
        $ready = $media->hasGeneratedConversion('storefront');
        $conversion = $ready ? 'storefront' : '';
        $disk = Storage::disk($ready ? ($media->conversions_disk ?: $media->disk) : $media->disk);
        $dimensions = false;
        if (config('filesystems.disks.'.($ready ? ($media->conversions_disk ?: $media->disk) : $media->disk).'.driver') === 'local') {
            $path = $disk->path($media->getPathRelativeToRoot($conversion));
            if (is_file($path)) {
                $dimensions = getimagesize($path);
            }
        }
        $responsive = $ready ? $media->getResponsiveImageUrls('storefront') : [];

        return [
            'id' => $media->id,
            'src' => $media->getUrl($conversion),
            'srcset' => $ready ? $media->getSrcset('storefront') : '',
            'original' => $media->getUrl(),
            'thumbnail' => $ready ? (end($responsive) ?: $media->getUrl('storefront')) : $media->getUrl(),
            'alt' => (string) $media->getCustomProperty('alt', ''),
            'purpose' => $media->getCustomProperty('placement') === 'detail' ? 'Color-neutral product detail' : null,
            'width' => $dimensions ? $dimensions[0] : 1600,
            'height' => $dimensions ? $dimensions[1] : 1600,
        ];
    }
}
