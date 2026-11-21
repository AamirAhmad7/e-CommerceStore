<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\ProductVariant;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

final class AddSizesAction
{
    public static function make(): Action
    {
        return Action::make('addSizes')->label('Add sizes')
            ->modalDescription('Preview and edit suggestions, then add pending rows. Nothing is saved until Save changes (or Create). Cancel keeps your existing form edits.')
            ->modalSubmitActionLabel('Add pending sizes')
            ->schema(function (Repeater $component): array {
                $refresh = function (Get $get, Set $set) use ($component): void {
                    [$rows, $summary] = self::preview($component, $get('sizes') ?? [], $get('source'), $get('rows') ?? []);
                    $set('rows', $rows);
                    $set('summary', $summary);
                };

                return [
                    Select::make('source')->label('Use sizes from...')->placeholder('Choose sizes below')->options(function () use ($component): array {
                        $colors = $component->getLivewire()->data['colors'] ?? [];
                        unset($colors[self::colorKey($component)]);

                        return collect($colors)->mapWithKeys(fn ($color, $key) => [$key => filled($color['name'] ?? '') ? $color['name'] : 'Unnamed color'])->all();
                    })->live()->afterStateUpdated($refresh),
                    TagsInput::make('sizes')->label('Sizes')->suggestions(['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'])->helperText('Choose a suggestion or type a custom/numeric size and press Enter. These are combined with the source color sizes.')->live()->afterStateUpdated($refresh),
                    Textarea::make('summary')->default('Name the destination color first, then choose sizes or a source color.')->readOnly()->dehydrated(false)->rows(2)->label('Preview'),
                    Repeater::make('rows')->label('New sizes and suggested SKUs')->defaultItems(0)->addable(false)->deletable(false)->reorderable(false)->schema([
                        TextInput::make('size')->readOnly()->required()->maxLength(30),
                        TextInput::make('sku')->label('Suggested SKU (editable)')->required()->maxLength(80)->regex('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/')->helperText(fn (Get $get) => $get('conflict') ?: 'No conflict found in the last preview. Checked again when applying.'),
                    ])->columns(2)->rules([fn () => function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                        if (! filled(self::destination($component)['name'] ?? '')) {
                            $fail('Enter the destination color name on the product form before generating SKUs.');

                            return;
                        }
                        foreach (self::conflicts($component, $value ?? []) as $message) {
                            $fail($message);
                        }
                    }]),
                ];
            })
            ->action(function (array $data, Repeater $component): void {
                $state = $component->getState() ?? [];
                $existing = array_map(fn ($row) => self::key($row['size'] ?? ''), $state);
                foreach ($data['rows'] ?? [] as $row) {
                    $key = self::key($row['size']);
                    if (in_array($key, $existing, true)) {
                        continue;
                    }
                    $state[(string) Str::uuid()] = ['size' => $row['size'], 'sku' => $row['sku'], 'is_active' => true, 'barcode' => null, 'weight_grams' => null, 'pkr_price_minor' => null, 'usd_price_minor' => null];
                    $existing[] = $key;
                }
                $component->state($state);
            })->successNotificationTitle('Pending sizes added. Save the product to persist them.');
    }

    private static function key(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private static function colorKey(Repeater $component): string
    {
        return explode('.', $component->getStatePath())[2];
    }

    private static function destination(Repeater $component): array
    {
        return $component->getLivewire()->data['colors'][self::colorKey($component)] ?? [];
    }

    private static function preview(Repeater $component, array $sizes, ?string $source, array $previous): array
    {
        $form = $component->getLivewire()->data;
        $destination = self::destination($component);
        if (! filled($destination['name'] ?? '')) {
            return [[], 'Enter the destination color name on the product form first. Cancel this dialog to name it; your edits are preserved.'];
        }
        $sourceSizes = $source && $source !== self::colorKey($component) ? array_column($form['colors'][$source]['variants'] ?? [], 'size') : [];
        $existing = collect($destination['variants'] ?? [])->keyBy(fn ($row) => self::key($row['size'] ?? ''));
        $previous = collect($previous)->keyBy(fn ($row) => self::key($row['size'] ?? ''));
        $rows = [];
        $present = [];
        $seen = [];
        foreach (array_merge($sourceSizes, $sizes) as $label) {
            $key = self::key($label);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if ($existing->has($key)) {
                $present[] = $existing[$key]['size'];

                continue;
            }
            $rows[] = ['size' => $label, 'sku' => $previous[$key]['sku'] ?? ProductForm::suggestSku($form['name'] ?? '', $destination['name'], $label)];
        }
        $conflicts = self::conflicts($component, $rows);
        foreach ($rows as $index => &$row) {
            $row['conflict'] = $conflicts[$index] ?? '';
        }
        unset($row);

        return [$rows, 'Will add: '.(implode(', ', array_column($rows, 'size')) ?: 'none').'. Already present: '.(implode(', ', $present) ?: 'none').'.'.($conflicts ? ' Edit the conflicting SKUs below.' : '')];
    }

    private static function conflicts(Repeater $component, array $rows): array
    {
        $databaseSkus = ProductVariant::whereIn('sku', array_column($rows, 'sku'))->pluck('sku')->map(fn ($sku) => self::key($sku))->all();
        $formSkus = collect($component->getLivewire()->data['colors'] ?? [])->flatMap(fn ($color) => $color['variants'] ?? [])->pluck('sku')->filter()->map(fn ($sku) => self::key($sku))->all();
        $present = array_map(fn ($row) => self::key($row['size'] ?? ''), self::destination($component)['variants'] ?? []);
        $seenSizes = [];
        $seenSkus = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            $size = self::key($row['size'] ?? '');
            $sku = self::key($row['sku'] ?? '');
            if (in_array($size, $present, true)) {
                continue;
            }
            if (isset($seenSizes[$size])) {
                $errors[$index] = $row['size'].': duplicate size in this batch.';
            }
            if (isset($seenSkus[$sku]) || in_array($sku, $databaseSkus, true) || in_array($sku, $formSkus, true)) {
                $errors[$index] = $row['size'].': SKU '.$row['sku'].' is already used. Enter a different code.';
            }
            $seenSizes[$size] = true;
            $seenSkus[$sku] = true;
        }

        return $errors;
    }
}
