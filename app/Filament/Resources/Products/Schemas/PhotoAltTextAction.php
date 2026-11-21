<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PhotoAltTextAction
{
    public static function make(): Action
    {
        return Action::make('editPhotoAltText')
            ->label(fn (SpatieMediaLibraryFileUpload $component): string => $component->getCollection() === 'shared' ? 'Photo use and alt text' : 'Edit photo alt text')
            ->modalHeading('Photo use and descriptions')
            ->modalDescription('Save new uploads first. Choose where each shared image appears. Awaiting classification is hidden; use Move shared photo for a color-specific photo. Describe the image in its alt text.')
            ->modalSubmitActionLabel('Save descriptions')
            ->visible(fn (SpatieMediaLibraryFileUpload $component): bool => $component->getRecord()?->exists ?? false)
            ->schema(function (SpatieMediaLibraryFileUpload $component): array {
                return $component->getRecord()->getMedia($component->getCollection())
                    ->map(fn (Media $photo): Section => Section::make($photo->file_name.' - '.($component->getCollection() === 'shared' ? match ($photo->getCustomProperty('placement')) {
                        'detail' => 'Product details', 'size_guide' => 'Size guide', default => 'Awaiting classification (hidden)'
                    } : 'Color gallery'))->schema([
                        Image::make($photo->getUrl(), (string) $photo->getCustomProperty('alt', ''))->imageHeight(120),
                        Textarea::make("alt.{$photo->uuid}")
                            ->label('Alt text')
                            ->maxLength(500)
                            ->rows(2)
                            ->default($photo->getCustomProperty('alt', '')),
                        ...($component->getCollection() === 'shared' ? [Select::make("placement.{$photo->uuid}")
                            ->label('Storefront placement')->required()
                            ->options(['unclassified' => 'Awaiting classification (hidden)', 'detail' => 'Product details (once, below the product)', 'size_guide' => 'Size guide (beside size selector)'])
                            ->default($photo->getCustomProperty('placement', 'unclassified'))] : []),
                    ]))->all();
            })
            ->action(function (array $data, SpatieMediaLibraryFileUpload $component): void {
                DB::transaction(function () use ($data, $component): void {
                    // Resolve ownership and collection on the server; never accept a media ID from the form.
                    $photos = $component->getRecord()->media()
                        ->where('collection_name', $component->getCollection())->get();

                    foreach ($photos as $photo) {
                        if ($component->getCollection() === 'shared' && isset($data['placement'][$photo->uuid])) {
                            $placement = $data['placement'][$photo->uuid];
                            abort_unless(in_array($placement, ['unclassified', 'detail', 'size_guide'], true), 422);
                            $photo->setCustomProperty('placement', $placement);
                        }
                        if (array_key_exists($photo->uuid, $data['alt'] ?? [])) {
                            $photo->setCustomProperty('alt', trim((string) $data['alt'][$photo->uuid]));
                        }
                        if ($photo->isDirty('custom_properties')) {
                            $photo->save();
                        }
                    }
                });
                $component->getRecord()->unsetRelation('media');
            })
            ->successNotificationTitle('Photo descriptions saved');
    }
}
