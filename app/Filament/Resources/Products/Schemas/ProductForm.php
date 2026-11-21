<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\ProductVariant;
use App\Support\ProductPresentation;
use App\Support\SeoFields;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            SeoFields::section('name', 'description'),
            Section::make('Storefront readiness')->schema([
                ViewEntry::make('readiness')->view('filament.product-readiness')
                    ->viewData(fn ($record) => ['findings' => $record?->exists ? ProductPresentation::readiness($record->fresh()) : []]),
            ])->columnSpanFull(),
            ...CommerceFields::prices(),
            TextInput::make('name')
                ->label('Product name')
                ->required()
                ->maxLength(160),

            Select::make('category_id')
                ->label('Category')
                ->relationship('category', 'name')
                ->searchable()
                ->preload()
                ->required(),

            TextInput::make('slug')
                ->label('URL slug')
                ->helperText('Example: classic-biker-jacket')
                ->required()
                ->maxLength(180)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->unique(ignoreRecord: true),

            Textarea::make('description')
                ->label('Product description')
                ->rows(5)
                ->maxLength(10000)
                ->columnSpanFull(),

            Section::make('Sizing and product details')->collapsed()->columnSpanFull()->schema([
                Select::make('size_guide_mode')->label('Measurement guide')->options(['shared' => 'Use a reusable guide', 'override' => 'Product-specific measurements'])->default('shared')->required()->live(),
                Select::make('size_guide_id')->label('Reusable size guide')->relationship('sizeGuide', 'name')->searchable()->preload()->visible(fn (Get $get): bool => $get('size_guide_mode') !== 'override')->helperText('Manage guides in Size guides. Existing uploaded size-guide images remain available.'),
                Group::make()->statePath('size_guide_override')->schema(SizeGuideFields::fields())->visible(fn (Get $get): bool => $get('size_guide_mode') === 'override'),
                ...array_map(fn (string $field) => Textarea::make($field)->label(ucfirst($field))->maxLength(2000)->rows(2), ['material', 'lining', 'fit', 'care']),
            ]),

            Toggle::make('is_active')
                ->label('Active')
                ->helperText('Leave this off while preparing the product.')
                ->default(false),

            SpatieMediaLibraryFileUpload::make('shared_photos')
                ->label('Product details and size-guide images')
                ->helperText('Save uploads, then choose Photo use and alt text to set each image to Product details or Size guide. Awaiting classification stays hidden. Color-specific photos belong under Product colors; use Move shared photo to move an existing upload without re-uploading.')
                ->collection('shared')
                ->hintAction(PhotoAltTextAction::make())
                ->disk(config('store.media_disk'))
                ->visibility('public')
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->multiple()
                ->reorderable()
                ->maxSize(8192)
                ->columnSpanFull(),

            Repeater::make('colors')
                ->label('Product colors')
                ->relationship('colors')
                ->addActionLabel('Add color')
                ->defaultItems(0)
                ->orderColumn('sort_order')
                ->reorderableWithButtons()
                ->itemLabel(fn (array $state): string => ($state['name'] ?? 'New color').' · '.count($state['variants'] ?? []).' sizes · '.count($state['color_photos'] ?? []).' photos')
                ->collapsed(fn ($item): bool => self::savedItemIsCollapsed($item))
                ->extraAttributes([
                    'id' => 'product-colors',
                    'x-on:form-validation-error.window' => '$nextTick(() => { for (const error of $el.querySelectorAll("[data-validation-error]")) { for (let node = error; node && $el.contains(node); node = node.parentElement) { node.dispatchEvent(new CustomEvent("expand")); } } })',
                ])
                ->deletable(false)
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Color name')
                        ->required()
                        ->maxLength(80)
                        ->distinct(),

                    ColorPicker::make('swatch_hex')
                        ->label('Color swatch'),

                    SpatieMediaLibraryFileUpload::make('color_photos')
                        ->label('Photos for this color')
                        ->helperText('Upload once for all sizes. Drag the cover photo into first position.')
                        ->collection('gallery')
                        ->hintAction(PhotoAltTextAction::make())
                        ->disk(config('store.media_disk'))
                        ->visibility('public')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->multiple()
                        ->reorderable()
                        ->maxSize(8192)
                        ->columnSpanFull(),

                    Repeater::make('variants')
                        ->label('Sizes for this color')
                        ->hintAction(AddSizesAction::make())
                        ->relationship('variants')
                        ->saveRelationshipsUsing(static function (Repeater $component): void {
                            $component->saveToRelationship();

                            // Gallery updates can rebuild the parent schema before it validates again.
                            // Replace new-item UUIDs with saved record keys so uniqueness ignores the owner.
                            $component->loadStateFromRelationships(shouldHydrate: true);
                        })
                        ->addActionLabel('Add size')
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->itemLabel(CommerceFields::variantLabel(...))
                        ->collapsed(fn ($item): bool => self::savedItemIsCollapsed($item))
                        ->deletable(false)
                        ->columnSpanFull()
                        ->columns(2)
                        ->schema([
                            ...CommerceFields::prices(),
                            CommerceFields::stock(),
                            TextInput::make('size')
                                ->label('Size')
                                ->helperText('Enter a size such as S, M, L, XL, or 42. Press Tab to suggest a SKU.')
                                ->required()
                                ->maxLength(30)
                                ->distinct()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (
                                    Get $get,
                                    Set $set,
                                    ?string $state,
                                    ?string $old,
                                    $record,
                                ): void {
                                    // An existing variant keeps its SKU when its size label changes.
                                    if ($record instanceof ProductVariant && $record->exists) {
                                        return;
                                    }

                                    // From a variant row, read its parent color and the root product.
                                    $productName = (string) $get('../../../../name');
                                    $colorName = (string) $get('../../name');
                                    $currentSku = (string) $get('sku');

                                    $oldSuggestion = self::suggestSku(
                                        $productName,
                                        $colorName,
                                        (string) $old,
                                    );

                                    // Preserve a SKU the admin entered manually.
                                    if ($currentSku !== '' && $currentSku !== $oldSuggestion) {
                                        return;
                                    }

                                    $suggestion = self::suggestSku(
                                        $productName,
                                        $colorName,
                                        (string) $state,
                                    );

                                    if ($suggestion !== $currentSku) {
                                        $set('sku', $suggestion, shouldCallUpdatedHooks: true);
                                    }
                                }),

                            TextInput::make('sku')
                                ->label('SKU')
                                ->extraFieldWrapperAttributes(fn (TextInput $component): array => [
                                    'x-data' => '{ skuCopyValue: "" }',
                                    'x-effect' => '$wire.$get('.Js::from($component->getStatePath()).'); $nextTick(() => skuCopyValue = $el.querySelector("input").value)',
                                    'x-on:input' => 'skuCopyValue = $event.target.value',
                                ])
                                ->suffixAction(Action::make('copySku')
                                    ->label('Copy SKU')
                                    ->icon('heroicon-o-clipboard-document')
                                    ->extraAttributes([
                                        'x-bind:disabled' => '!skuCopyValue.trim()',
                                        'x-on:mousedown.prevent' => '',
                                    ])
                                    ->alpineClickHandler(<<<'JS'
                                        const input = $el.closest('[data-field-wrapper]').querySelector('input');
                                        const value = input.value;
                                        if (!value.trim()) return;
                                        Promise.resolve().then(() => navigator.clipboard.writeText(value))
                                            .then(() => new FilamentNotification().title('SKU copied').success().send())
                                            .catch(() => {
                                                input.focus();
                                                input.select();
                                                new FilamentNotification().title('Clipboard unavailable. Copy the selected SKU manually.').warning().send();
                                            });
                                        JS))
                                ->helperText('Suggested from product, color, and size. You can edit it.')
                                ->required()
                                ->maxLength(80)
                                ->regex('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/')
                                ->unique(
                                    table: 'product_variants',
                                    column: 'sku',
                                    ignoreRecord: true,
                                )
                                ->validationMessages([
                                    'unique' => 'This SKU belongs to another variant. Enter a different code.',
                                    'regex' => 'Use uppercase letters, numbers, and hyphens, such as CBJ-BLK-XL.',
                                ])
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (TextInput $component, $livewire): void {
                                    $livewire->validateOnly($component->getStatePath());
                                }),

                            TextInput::make('barcode')
                                ->label('Barcode')
                                ->maxLength(64)
                                ->unique(ignoreRecord: true),

                            TextInput::make('weight_grams')
                                ->label('Weight in grams')
                                ->integer()
                                ->minValue(1)
                                ->maxValue(4294967295),

                            Toggle::make('is_active')
                                ->label('Variant active')
                                ->default(true),
                        ]),
                ]),
        ]);
    }

    private static function savedItemIsCollapsed(?Schema $item): bool
    {
        if (! $item || ! ($item->getRecord() instanceof Model) || ! $item->getRecord()->exists) {
            return false;
        }
        foreach ($item->getLivewire()->getErrorBag()->keys() as $path) {
            if (str_starts_with($path, $item->getStatePath().'.')) {
                return false;
            }
        }

        return true;
    }

    public static function suggestSku(
        string $productName,
        string $colorName,
        string $size,
    ): string {
        $productSlug = Str::slug($productName);
        $colorSlug = Str::slug($colorName);
        $sizeCode = strtoupper(Str::slug($size));

        if ($productSlug === '' || $colorSlug === '' || $sizeCode === '') {
            return '';
        }

        $initials = '';

        foreach (explode('-', $productSlug) as $word) {
            $initials .= substr($word, 0, 1);
        }

        $productCode = strtoupper(substr($initials, 0, 4));

        $colorCode = match ($colorSlug) {
            'black', 'jet-black' => 'BLK',
            'brown' => 'BRN',
            'white' => 'WHT',
            'navy', 'navy-blue' => 'NVY',
            'grey', 'gray' => 'GRY',
            default => strtoupper(substr(str_replace('-', '', $colorSlug), 0, 3)),
        };

        return "{$productCode}-{$colorCode}-{$sizeCode}";
    }
}
