<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\Inventory;
use App\Services\Pricing;
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
            ->label($currency.' price')->helperText('Optional. Variant prices override the product base price.')
            ->inputMode('decimal')->regex('/^\d{1,8}(?:\.\d{1,2})?$/')->nullable()
            ->formatStateUsing(fn ($state) => Pricing::decimal($state === null ? null : (int) $state, $currency))
            ->dehydrateStateUsing(fn ($state) => Pricing::parse($state, $currency)), ['USD', 'PKR']);
    }

    public static function stock(): TextEntry
    {
        return TextEntry::make('stock_summary')->label('Stock for this size')->columnSpanFull()
            ->state(fn ($record) => $record instanceof ProductVariant && $record->exists
                ? self::stockLabel($record->fresh())
                : 'Save this size to enter stock.')
            ->helperText('On hand is the physical balance, including reserved units. Reserved is committed to orders. Available = on hand minus reserved, once.')
            ->hintActions($actions = [
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

        return new HtmlString($label.($record instanceof ProductVariant && $record->exists ? ' — '.self::stockLabel($record->fresh())->toHtml() : ' — Save to enter opening stock'));
    }

    private static function stockLabel(ProductVariant $record): HtmlString
    {
        return new HtmlString('<strong>Available: '.Inventory::available($record).'</strong> · On hand: '.($record->on_hand ?? 'Not configured').' · Reserved: '.$record->reserved);
    }
}
