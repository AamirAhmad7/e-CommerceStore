<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product;
use App\Services\Pricing;
use App\Services\VariantPricing;
use App\Support\ProductWorkspace;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ManagePricesAction
{
    public static function make(): Action
    {
        return Action::make('managePrices')->label('Manage prices')->visible(fn (): bool => Gate::allows('manage-commerce'))
            ->modalHeading('Manage saved variant prices')->modalWidth('5xl')
            ->modalDescription('Save newly added sizes before pricing them. Select one SKU, a color, or all saved sizes. Prices below are saved effective prices. Confirm saves prices immediately; other product edits remain pending. Save any pending price-field edits first.')
            ->modalSubmitActionLabel('Confirm previewed prices')
            ->fillForm(function (Product $record, array $arguments): array {
                VariantPricing::authorize($record);
                $product = $record->fresh(['colors.variants']);
                $focused = $product->colors->flatMap->variants->firstWhere('id', $arguments['variant'] ?? null);
                $focusedPrice = $focused ? Pricing::resolve($focused, 'PKR', $product) : null;
                $baseline = ['product' => $product->only(['pkr_price_minor', 'usd_price_minor']), 'variants' => $product->colors->flatMap->variants->keyBy('id')->map->only(['pkr_price_minor', 'usd_price_minor'])->all()];

                return ['baseline' => Crypt::encryptString(json_encode($baseline, JSON_THROW_ON_ERROR)), 'selection' => isset($arguments['variant']) ? $product->colors->mapWithKeys(fn ($color) => [$color->id => $color->variants->where('id', $arguments['variant'])->modelKeys()])->all() : [], 'currency' => 'PKR', 'mode' => $focusedPrice !== null && $focusedPrice <= 0 ? 'replace' : 'missing', 'scope' => 'custom', 'preview' => 'Choose saved sizes and an amount, then click Preview prices.', 'token' => ''];
            })
            ->schema(function (Product $record): array {
                $colors = $record->fresh(['colors.variants'])->colors;
                $product = $record->fresh();
                $invalidate = function (Set $set): void {
                    $set('token', '');
                    $set('preview', 'Selection or input changed. Click Preview prices before confirming.');
                };

                return [
                    Hidden::make('baseline')->required(), Hidden::make('token'),
                    Select::make('scope')->label('Quick selection')->options(['custom' => 'Choose individual sizes', 'all' => 'All saved variants'] + $colors->mapWithKeys(fn ($color) => [(string) $color->id => 'All '.$color->name.' sizes'])->all())
                        ->live()->afterStateUpdated(function ($state, Set $set) use ($colors): void {
                            $set('selection', $colors->mapWithKeys(fn ($color) => [(string) $color->id => $state === 'all' || (string) $state === (string) $color->id ? $color->variants->modelKeys() : []])->all());
                            $set('token', '');
                            $set('preview', 'Selection changed. Click Preview prices before confirming.');
                        }),
                    ...$colors->map(fn ($color) => Section::make($color->name)->schema([
                        CheckboxList::make('selection.'.$color->id)->label('Size / SKU / effective prices')->options($color->variants->mapWithKeys(function ($variant) use ($product): array {
                            $prices = collect(['PKR', 'USD'])->map(function ($currency) use ($variant, $product): string {
                                $field = strtolower($currency).'_price_minor';
                                $price = Pricing::resolve($variant, $currency, $product);

                                return $currency.': '.($price === null ? 'Not set' : Pricing::decimal($price, $currency).' ('.($variant->$field === null ? 'product price' : 'variant override').')');
                            })->implode(' | ');

                            return [$variant->id => $variant->size.' / '.$variant->sku.' | '.$prices];
                        })->all())->bulkToggleable()->live()->afterStateUpdated($invalidate),
                    ]))->all(),
                    TextEntry::make('selected_count')->label('Selected count')->state(fn (Get $get): int => count(self::ids($get('selection') ?? []))),
                    Select::make('currency')->options(['PKR' => 'PKR', 'USD' => 'USD'])->required()->live()->afterStateUpdated($invalidate),
                    Select::make('mode')->label('Update mode')->options(['missing' => 'Fill missing prices only', 'replace' => 'Replace prices for selected variants'])->required()->live()->afterStateUpdated($invalidate)
                        ->helperText('Missing means no variant override and no inherited product price. Existing prices, including zero, are preserved in fill-missing mode.'),
                    TextInput::make('amount')->label('Price per selected variant')->required()->regex('/^\d{1,8}(?:\.\d{1,2})?$/')->inputMode('decimal')->live(onBlur: true)->afterStateUpdated($invalidate)->helperText('Enter a positive amount with at most two decimal places. PKR and USD are independent. Blank never clears a price.'),
                    Actions::make([Action::make('previewPrices')->label('Preview prices')->action(function (Get $get, Set $set, EditProduct $livewire) use ($record): void {
                        try {
                            self::guardPendingPrices($livewire, $get('baseline'), self::ids($get('selection') ?? []), $get('currency'));
                            $plan = app(VariantPricing::class)->preview($record, self::ids($get('selection') ?? []), $get('currency'), $get('mode'), $get('amount'));
                            $set('token', $plan['token']);
                            $set('preview', collect($plan['rows'])->map(fn ($row) => $row['sku'].' | '.($row['current'] === null ? 'Not set' : Pricing::format($row['current'], $plan['currency'])).' -> '.($row['proposed'] === null ? 'Not set' : Pricing::format($row['proposed'], $plan['currency'])).' | '.($row['change'] ? 'Will change' : 'Skipped (existing/unchanged)'))->implode("\n"));
                        } catch (ValidationException $exception) {
                            $set('token', '');
                            Notification::make()->danger()->title('Review prices')->body(collect($exception->errors())->flatten()->implode(' '))->send();
                        }
                    })]),
                    Textarea::make('preview')->label('SKU / current / proposed / result')->readOnly()->dehydrated(false)->rows(8),
                ];
            })
            ->action(function (array $data, Product $record, EditProduct $livewire): void {
                try {
                    self::guardPendingPrices($livewire, $data['baseline'], self::ids($data['selection'] ?? []), $data['currency']);
                    $plan = app(VariantPricing::class)->apply($record, self::ids($data['selection'] ?? []), $data['currency'], $data['mode'], $data['amount'], $data['token'] ?? '');
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title('Review prices')->body(collect($exception->errors())->flatten()->implode(' '))->send();
                    throw new Halt;
                }
                $field = strtolower($plan['currency']).'_price_minor';
                foreach ($plan['rows'] as $row) {
                    $path = 'colors.record-'.$row['color_id'].'.variants.record-'.$row['id'];
                    if (data_get($livewire->data, $path) !== null) {
                        data_set($livewire->data, $path.'.'.$field, Pricing::decimal($row['change'] ? $row['proposed'] : $row['raw'], $plan['currency']));
                    }
                }
                $changed = count(array_filter($plan['rows'], fn ($row) => $row['change']));
                ProductWorkspace::forget();
                Notification::make()->success()->title('Updated '.$changed.' '.$plan['currency'].' prices; skipped '.(count($plan['rows']) - $changed).' existing or unchanged prices')->send();
            });
    }

    private static function ids(array $selection): array
    {
        return collect($selection)->flatten()->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }

    private static function guardPendingPrices(EditProduct $page, string $token, array $ids, string $currency): void
    {
        $baseline = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        $field = strtolower(Pricing::currency($currency)).'_price_minor';
        $different = Pricing::parse($page->data[$field] ?? null, $currency) !== ($baseline['product'][$field] === null ? null : (int) $baseline['product'][$field]);
        foreach ($page->data['colors'] ?? [] as $color) {
            foreach ($color['variants'] ?? [] as $key => $variant) {
                $id = (int) str_replace('record-', '', (string) $key);
                if (in_array($id, $ids, true) && array_key_exists($id, $baseline['variants'])) {
                    $different = $different || Pricing::parse($variant[$field] ?? null, $currency) !== ($baseline['variants'][$id][$field] === null ? null : (int) $baseline['variants'][$id][$field]);
                }
            }
        }
        if ($different) {
            throw ValidationException::withMessages(['prices' => 'Save your pending '.$currency.' price-field edits before managing these prices. Other product edits can remain pending.']);
        }
    }
}
