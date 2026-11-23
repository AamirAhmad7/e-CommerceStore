<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\ProductVariant;
use App\Services\Pricing;
use App\Services\VariantRemoval;
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
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        $components = [
            SeoFields::section('name', 'description'),
            Section::make('At a glance')->compact()->schema([
                ViewEntry::make('readiness')->view('filament.product-readiness')
                    ->viewData(fn ($record) => ['findings' => $record?->exists ? ProductPresentation::readiness($record->fresh()) : []]),
            ])->columnSpanFull(),
            ...CommerceFields::prices(),
            TextInput::make('name')
                ->label('Product name')
                ->required()
                ->maxLength(160)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?string $old, $record): void {
                    if (! $record?->exists && (! $get('slug') || $get('slug') === Str::slug($old ?? ''))) {
                        $set('slug', Str::slug($state ?? ''));
                    }
                }),

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
                Textarea::make('closure')->label('Closure / hardware')->maxLength(500)->rows(2)->visible(fn (): bool => \Illuminate\Support\Facades\Schema::hasColumn('products', 'closure')),
                ...array_map(fn (string $field) => Textarea::make($field)->label(ucfirst($field))->maxLength(2000)->rows(2), ['material', 'lining', 'fit', 'care']),
            ]),

            Toggle::make('is_active')->extraAttributes(['id' => 'product-publication'])
                ->label('Visible in shop')
                ->helperText('Off keeps this product as a draft.')
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
                ->itemLabel(CommerceFields::colorLabel(...))
                ->expandAction(fn (Action $action) => $action->label('Edit color')->icon('heroicon-o-pencil-square')->button())
                ->collapsed(fn ($item): bool => self::savedItemIsCollapsed($item))
                ->extraAttributes([
                    'id' => 'product-colors',
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

                    Section::make('Color photos')->collapsed()->columnSpanFull()->schema([
                        SpatieMediaLibraryFileUpload::make('color_photos')
                            ->label(fn (Get $get): string => ($get('name') ?: 'Color').' gallery')
                            ->extraAttributes(fn ($record): array => ['id' => 'color-'.($record?->id ?? 'new').'-photos'])
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

                    ]),
                    Section::make('Model in this color gallery')->visible(fn (): bool => \Illuminate\Support\Facades\Schema::hasColumn('product_colors', 'model_height_cm'))->collapsed()->columnSpanFull()->schema([
                        TextInput::make('model_height_cm')->label('Height (cm)')->integer()->minValue(50)->maxValue(250)->nullable(),
                        TextInput::make('model_size_worn')->label('Size worn')->maxLength(30)->nullable(),
                    ])->description('Optional. Enter both to show a caption for this color. Use actual details of the person in these photos.'),
                    Repeater::make('variants')
                        ->label('Sizes & availability')->helperText('Expand a size to edit it. Stock and price summaries show saved values; pending form edits are applied with Save changes.')
                        ->extraAttributes(fn ($record): array => ['id' => 'color-'.($record?->id ?? 'new').'-sizes', 'class' => 'sf-size-editor'])
                        ->hintAction(AddSizesAction::make())
                        ->relationship('variants')
                        ->saveRelationshipsUsing(static function (Repeater $component): void {
                            DB::transaction(function () use ($component): void {
                                $state = $component->getRawState() ?? [];
                                foreach ($component->getCachedExistingRecords() as $key => $existing) {
                                    if (array_key_exists($key, $state)) {
                                        continue;
                                    }
                                    Gate::authorize('manage-commerce');
                                    $locked = ProductVariant::whereKey($existing->id)->where('product_color_id', $component->getRecord()->id)->lockForUpdate()->firstOrFail();
                                    VariantRemoval::requireUnused($locked, $component->getStatePath());
                                }
                                $component->saveToRelationship();
                            });

                            // Gallery updates can rebuild the parent schema before it validates again.
                            // Replace new-item UUIDs with saved record keys so uniqueness ignores the owner.
                            $component->loadStateFromRelationships(shouldHydrate: true);
                        })
                        ->addActionLabel('Add size')
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->itemLabel(CommerceFields::variantLabel(...))
                        ->extraItemActions([
                            Action::make('rowPrice')->label('Fix / edit price')->icon('heroicon-o-banknotes')->button()
                                ->visible(fn (array $arguments, Repeater $component): bool => Gate::allows('manage-commerce') && $component->getChildSchema($arguments['item'])->getRecord()?->exists === true)
                                ->action(fn (array $arguments, Repeater $component, $livewire) => $livewire->replaceMountedAction('managePrices', ['variant' => $component->getChildSchema($arguments['item'])->getRecord()->id])),
                            Action::make('rowStock')->label('Fix / edit stock')->icon('heroicon-o-cube')->button()
                                ->visible(fn (array $arguments, Repeater $component): bool => Gate::allows('manage-commerce') && $component->getChildSchema($arguments['item'])->getRecord()?->exists === true)
                                ->action(fn (array $arguments, Repeater $component, $livewire) => $livewire->replaceMountedAction('manageStock', ['variant' => $component->getChildSchema($arguments['item'])->getRecord()->id])),
                        ])
                        ->expandAction(fn (Action $action) => $action->label('Edit size')->icon('heroicon-o-pencil-square')->button())
                        ->collapsed(fn ($item): bool => self::savedItemIsCollapsed($item))
                        ->deletable(fn (): bool => Gate::allows('manage-commerce'))
                        ->deleteAction(fn (Action $action) => $action->label('Delete size')->icon('heroicon-o-trash')->button()
                            ->requiresConfirmation(fn (array $arguments, Repeater $component): bool => $component->getChildSchema($arguments['item'])->getRecord() instanceof ProductVariant)
                            ->modalHeading('Delete this size?')
                            ->modalDescription(function (array $arguments, Repeater $component): string {
                                $record = $component->getChildSchema($arguments['item'])->getRecord();
                                if (! $record instanceof ProductVariant) {
                                    return 'Remove this unsaved size.';
                                }
                                $reasons = VariantRemoval::blockers($record->fresh());

                                return $record->productColor->name.' / '.$record->size.' / '.$record->sku.'. '.($reasons ? 'Cannot delete: '.implode(', ', $reasons).'. Use Edit size and turn Variant active off instead.' : 'This unused size will be deleted when you Save changes. Color photos are retained. Canceling the product edit discards this removal.');
                            })
                            ->before(function (array $arguments, Repeater $component): void {
                                Gate::authorize('manage-commerce');
                                $record = $component->getChildSchema($arguments['item'])->getRecord();
                                if ($record instanceof ProductVariant) {
                                    $reasons = VariantRemoval::blockers($record->fresh());
                                    if ($reasons) {
                                        Notification::make()->warning()->title('Size cannot be deleted')->body(implode(', ', $reasons).'. Edit this size and turn Variant active off instead.')->send();
                                        throw new Halt;
                                    }
                                }
                            }))
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
        ];

        [$seo, $readiness, $usdPrice, $pkrPrice, $name, $category, $slug, $description, $sizing, $active, $sharedPhotos, $colors] = $components;

        return $schema->columns(1)->components([
            Group::make([
                Section::make('Product details')->description('Name and storefront visibility')->icon('heroicon-o-tag')
                    ->columns(['default' => 1, 'md' => 2])->schema([$name, $active])->columnSpan(['default' => 1, 'xl' => 2]),
                $readiness->icon('heroicon-o-check-circle')->columnSpan(1),
            ])->columns(['default' => 1, 'xl' => 3])->columnSpanFull(),
            Tabs::make('Product editor')->id('product-editor-tabs')->columnSpanFull()->tabs([
                Tab::make('General')->icon('heroicon-o-pencil-square')->schema([$category, $slug, $description])->columns(2),
                Tab::make('Pricing')->icon('heroicon-o-banknotes')->schema([
                    $pkrPrice->label('Pakistan selling price')->prefix('PKR'),
                    $usdPrice->label('International selling price')->prefix('USD'),
                    TextInput::make('delivery_charge_minor')->label('Product delivery charge')->prefix('PKR')
                        ->visible(fn (): bool => \Illuminate\Support\Facades\Schema::hasColumn('products', 'delivery_charge_minor'))
                        ->inputMode('decimal')->regex('/^\d{1,8}(?:\.\d{1,2})?$/')->nullable()
                        ->formatStateUsing(fn ($state) => Pricing::decimal($state === null ? null : (int) $state))
                        ->dehydrateStateUsing(fn ($state) => Pricing::parse($state, 'PKR'))
                        ->helperText('Applies to every color and size. Enter 0 for free delivery; blank uses Settings > Commerce settings. The highest applicable charge is added once per order, regardless of quantity. The store free-delivery threshold still applies.')
                        ->columnSpanFull(),
                ])->columns(2),
                Tab::make('Colors & sizes')->icon('heroicon-o-squares-2x2')->id('product-variants-tab')->schema([$colors]),
                Tab::make('Photos')->icon('heroicon-o-photo')->schema([
                    ViewEntry::make('gallery_navigation')->view('filament.product-gallery-navigation')
                        ->viewData(fn ($record) => ['colors' => $record?->colors ?? collect()]),
                    $sharedPhotos,
                ]),
                Tab::make('SEO & sharing')->icon('heroicon-o-magnifying-glass')->schema([$seo->collapsed(false)->collapsible(false)->contained(false)->columns(['default' => 1, 'lg' => 2])]),
                Tab::make('Size guide')->icon('heroicon-o-adjustments-horizontal')->schema([$sizing->collapsed(false)->collapsible(false)->contained(false)]),
            ])->extraAttributes([
                'x-on:form-validation-error.window' => e('$nextTick(() => { const error = $el.querySelector("[data-validation-error]"); if (!error) return; for (let node = error; node && $el.contains(node); node = node.parentElement) node.dispatchEvent(new CustomEvent("expand")); $nextTick(() => error.closest("[data-field-wrapper]")?.querySelector("input, select, textarea, button")?.focus()); })'),
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
