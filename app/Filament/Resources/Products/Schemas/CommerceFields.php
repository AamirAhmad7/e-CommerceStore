<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\Inventory;
use App\Services\Pricing;
use App\Support\ProductWorkspace;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

final class CommerceFields
{
    public static function prices(): array
    {
        return array_map(fn (string $currency) => TextInput::make(strtolower($currency).'_price_minor')
            ->extraFieldWrapperAttributes(['data-price-currency' => $currency])->label($currency.' price')->helperText('Optional. Variant prices override the product base price.')
            ->inputMode('decimal')->regex('/^\d{1,8}(?:\.\d{1,2})?$/')->nullable()
            ->formatStateUsing(fn ($state) => Pricing::decimal($state === null ? null : (int) $state, $currency))
            ->dehydrateStateUsing(fn ($state) => Pricing::parse($state, $currency)), ['USD', 'PKR']);
    }

    public static function stock(): TextEntry
    {
        return TextEntry::make('stock_summary')->label('Stock for this size')->columnSpanFull()
            ->state(fn ($record) => $record instanceof ProductVariant && $record->exists
                ? self::stockLabel(ProductWorkspace::variant($record))
                : 'Save this size to enter stock.')
            ->helperText('On hand is the physical balance, including reserved units. Reserved is committed to orders. Available = on hand minus reserved, once.')
            ->hintActions($actions = [
                Action::make('editSizePrice')->label('Manage price')->visible(fn ($record) => $record instanceof ProductVariant && $record->exists && Gate::allows('manage-commerce'))
                    ->action(fn ($record, $livewire) => $livewire->replaceMountedAction('managePrices', ['variant' => $record->id])),
                Action::make('adjustStock')->label('Manage stock')->visible(fn ($record) => $record instanceof ProductVariant && $record->exists && Gate::allows('manage-commerce'))
                    ->action(fn ($record, $livewire) => $livewire->replaceMountedAction('manageStock', ['variant' => $record->id])),
                Action::make('stockHistory')->label('Stock history')->visible(fn ($record) => $record instanceof ProductVariant && $record->exists)
                    ->modalSubmitAction(false)->modalContent(fn ($record) => view('filament.stock-history', ['movements' => StockMovement::where('product_variant_id', $record->id)->latest('id')->limit(100)->get()])),
            ])->registerActions($actions);
    }

    public static function variantLabel(array $state, Schema $item): HtmlString
    {
        $label = e(($state['size'] ?? 'New size').' · '.($state['sku'] ?? 'SKU pending'));
        $record = $item->getRecord();

        $product = $item->getLivewire()->getRecord();
        if ($record instanceof ProductVariant && $record->exists && $product instanceof Product) {
            $variant = ProductWorkspace::variant($record);
            $price = Pricing::resolve($variant, 'PKR', $product);
            $status = ! $variant->is_active ? 'Inactive' : ($variant->on_hand === null ? 'Stock has never been entered' : (Inventory::available($variant) === 0 ? ($variant->on_hand > 0 ? 'All units reserved' : 'Sold out') : ($price <= 0 ? 'Needs PKR price' : (! $product->is_active || ! $product->category?->is_active ? 'Hidden by publication' : 'Available to order'))));

            return new HtmlString(view('filament.variant-summary', [
                'size' => $state['size'] ?? $variant->size,
                'sku' => $state['sku'] ?? $variant->sku,
                'pkr' => $price,
                'usd' => Pricing::resolve($variant, 'USD', $product),
                'available' => Inventory::available($variant),
                'onHand' => $variant->on_hand,
                'reserved' => $variant->reserved,
                'status' => $status,
                'ready' => $status === 'Available to order',
            ])->render());
        }

        return new HtmlString($label.($record instanceof ProductVariant && $record->exists ? ' — '.self::stockLabel(ProductWorkspace::variant($record))->toHtml() : ' — Save to enter opening stock'));
    }

    public static function colorLabel(array $state, Schema $item): HtmlString
    {
        $color = $item->getRecord();
        $product = $item->getLivewire()->getRecord();
        $available = 0;
        if ($color instanceof ProductColor && $product instanceof Product) {
            foreach ($color->variants as $variant) {
                $variant = ProductWorkspace::variant($variant);
                if ($product->is_active && $product->category?->is_active && $variant->is_active && Inventory::available($variant) > 0 && Pricing::resolve($variant, 'PKR', $product) > 0) {
                    $available++;
                }
            }
        }

        $name = $state['name'] ?? 'New color';
        $swatch = $state['swatch_hex'] ?? null;
        if (! is_string($swatch) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $swatch)) {
            $swatch = match (strtolower(trim($name))) {
                'black' => '#222222',
                'brown' => '#805538',
                default => '#64748b',
            };
        }

        return new HtmlString(view('filament.color-summary', [
            'name' => $name,
            'swatch' => $swatch,
            'sizes' => $color instanceof ProductColor && $color->exists ? $color->variants->count() : 0,
            'photos' => $color instanceof ProductColor && $color->exists ? $color->getMedia('gallery')->count() : 0,
            'available' => $available,
            'saved' => $color instanceof ProductColor && $color->exists,
        ])->render());
    }

    private static function stockLabel(ProductVariant $record): HtmlString
    {
        return new HtmlString('<strong>Available: '.Inventory::available($record).'</strong> · On hand: '.($record->on_hand ?? 'Never entered').' · Reserved: '.$record->reserved);
    }
}
