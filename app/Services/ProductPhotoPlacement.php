<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

final class ProductPhotoPlacement
{
    /** Both models use identical conversions and media-ID paths; preserve the media row and all derived files. */
    public function moveToColor(Product $product, int $mediaId, int $colorId): void
    {
        DB::transaction(function () use ($product, $mediaId, $colorId): void {
            $locked = Product::lockForUpdate()->findOrFail($product->id);
            $color = $locked->colors()->lockForUpdate()->find($colorId);
            $photo = $locked->media()->where('collection_name', 'shared')->lockForUpdate()->find($mediaId);
            if (! $color || ! $photo) {
                throw ValidationException::withMessages(['photo' => 'Choose a current shared photo and a color belonging to this product.']);
            }
            if (get_class(PathGeneratorFactory::create($photo)) !== DefaultPathGenerator::class) {
                throw ValidationException::withMessages(['photo' => 'This storage path configuration needs a file-aware move.']);
            }
            $photo->model()->associate($color);
            if (get_class(PathGeneratorFactory::create($photo)) !== DefaultPathGenerator::class) {
                throw ValidationException::withMessages(['photo' => 'The destination storage configuration needs a file-aware move.']);
            }
            $photo->collection_name = 'gallery';
            $photo->order_column = (int) $color->media()->where('collection_name', 'gallery')->max('order_column') + 1;
            $photo->forgetCustomProperty('placement');
            $photo->save();
        }, 3);
    }
}
