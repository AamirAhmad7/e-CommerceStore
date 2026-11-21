<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SupplierSource;
use App\Services\Pricing;
use App\Services\PurchaseReceiving;
use App\Services\Purchasing;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class PurchaseForms
{
    public static function fields(): array
    {
        return [
            Hidden::make('version'),
            Select::make('supplier_id')->label('Supplier')->options(fn () => Supplier::orderBy('name')->get()->mapWithKeys(fn ($s) => [$s->id => $s->name.($s->is_active ? '' : ' [archived]')])->all())->searchable()->required()->live()->afterStateUpdated(function (Set $set): void {
                $set('lines', []);
                $set('grid', []);
                $set('grid_product', null);
            }),
            DatePicker::make('expected_at')->label('Expected arrival (optional)'), TextInput::make('supplier_reference')->maxLength(255), TextInput::make('freight')->label('Freight estimate PKR (optional)')->inputMode('decimal')->regex('/^\d{1,8}(?:\.\d{1,2})?$/'), Textarea::make('notes')->label('Internal notes (not printed)')->maxLength(3000),
            Select::make('grid_product')->label('Quick size grid: choose a product')->options(fn (Get $get) => Product::whereHas('colors.variants', fn ($q) => $q->whereIn('id', SupplierSource::where('supplier_id', $get('supplier_id'))->select('product_variant_id')))->orderBy('name')->limit(100)->pluck('name', 'id')->all())->searchable()->live()->afterStateUpdated(fn (Set $set) => $set('grid', [])),
            Hidden::make('grid'),
            ViewEntry::make('size_grid')->view('filament.purchase-size-grid')->viewData(fn (Get $get): array => ['sources' => self::gridSources($get)])->columnSpanFull(),
            Actions::make([Action::make('addGrid')->label('Add grid quantities to order')->action(function (Get $get, Set $set): void {
                $lines = collect($get('lines') ?? [])->keyBy('variant_id');
                foreach (self::gridSources($get) as $source) {
                    $qty = ($get('grid') ?? [])[$source->product_variant_id] ?? null;
                    if ($qty === null || $qty === '') {
                        continue;
                    }
                    if (filter_var($qty, FILTER_VALIDATE_INT) === false || (int) $qty < 1 || (int) $qty > 100000) {
                        CommerceAction::run(fn () => Purchasing::invalid('Grid quantities must be positive whole units; leave unwanted sizes blank.'));
                    }
                    $old = $lines->get($source->product_variant_id);
                    $lines->put($source->product_variant_id, ['variant_id' => $source->product_variant_id, 'quantity' => (int) $qty + (int) ($old['quantity'] ?? 0), 'cost' => $old['cost'] ?? Pricing::decimal($source->quoted_cost_minor)]);
                }
                $set('lines', $lines->values()->all());
                $set('grid', []);
            })]),
            Repeater::make('lines')->label('Order lines')->minItems(1)->maxItems(200)->defaultItems(0)->reorderable(false)->schema([
                Select::make('variant_id')->label('Search store/supplier SKU, product, color or size')->required()->searchable()
                    ->getSearchResultsUsing(function (string $search, Get $get): array {
                        return SupplierSource::with('variant.productColor.product')->where('supplier_id', $get('../../supplier_id'))->where(fn ($q) => $q->where('supplier_sku', 'like', '%'.$search.'%')->orWhereHas('variant', fn ($v) => $v->where('sku', 'like', '%'.$search.'%')->orWhere('size', 'like', '%'.$search.'%')->orWhereHas('productColor', fn ($c) => $c->where('name', 'like', '%'.$search.'%')->orWhereHas('product', fn ($p) => $p->where('name', 'like', '%'.$search.'%')))))->limit(40)->get()->mapWithKeys(fn ($s) => [$s->product_variant_id => Purchasing::variantLabel($s->variant).' / supplier SKU '.$s->supplier_sku])->all();
                    })->getOptionLabelUsing(fn ($value) => ($v = ProductVariant::with('productColor.product')->find($value)) ? Purchasing::variantLabel($v) : 'Archived SKU')->live()->afterStateUpdated(function ($state, Get $get, Set $set): void {
                        $s = SupplierSource::where('supplier_id', $get('../../supplier_id'))->where('product_variant_id', $state)->first();
                        $set('cost', Pricing::decimal($s?->quoted_cost_minor));
                    }),
                TextInput::make('quantity')->integer()->minValue(1)->maxValue(100000)->required()->live(onBlur: true),
                TextInput::make('cost')->label('Agreed unit cost PKR (blank = unknown)')->inputMode('decimal')->regex('/^\d{1,8}(?:\.\d{1,2})?$/')->nullable()->live(onBlur: true),
            ])->columns(3)->columnSpanFull(),
            ViewEntry::make('purchase_totals')->view('filament.purchase-totals')->viewData(fn (Get $get): array => ['rows' => $get('lines') ?? [], 'freight' => $get('freight')])->columnSpanFull(),
        ];
    }

    private static function gridSources(Get $get): Collection
    {
        return SupplierSource::with('variant.productColor.product')->where('supplier_id', $get('supplier_id'))->whereHas('variant.productColor', fn ($q) => $q->where('product_id', $get('grid_product') ?? 0))->get();
    }

    public static function receiptAction(bool $correction = false): Action
    {
        return Action::make($correction ? 'correctReceipt' : 'receiveDelivery')->label($correction ? 'Correct receipt' : 'Receive delivery')->modalWidth('7xl')
            ->modalDescription($correction ? 'Enter signed changes, not replacement totals. Original receipts remain. Negative accepted quantities must respect customer reservations.' : 'Accepted units enter stock. Damaged units never do. Put damaged quantities in the replacement-expected or shortfall-accepted column. Blank cells skip.')
            ->fillForm(function ($record) use ($correction): array {
                $order = $correction ? $record->order : $record;
                $order = $order->fresh(['lines']);
                $selected = $correction ? $order->lines->whereIn('id', $record->lines->pluck('purchase_order_line_id')) : $order->lines;
                $rows = $selected->mapWithKeys(fn ($l) => [$l->id => ['accepted' => null, 'damaged_replace' => null, 'damaged_short' => null, 'short_closed' => null, 'cost' => Pricing::decimal($correction ? $record->lines->firstWhere('purchase_order_line_id', $l->id)?->unit_cost_minor : $l->unit_cost_minor)]])->all();

                return ['rows' => $rows, 'baseline' => PurchaseReceiving::baseline($order), 'key' => (string) Str::uuid(), 'token' => ''];
            })->schema([
                Hidden::make('baseline'), Hidden::make('key'), Hidden::make('token'), Hidden::make('rows'),
                Actions::make([Action::make('fillRemaining')->label('Fill remaining quantities as accepted')->visible(! $correction)->action(function ($record, Set $set): void {
                    $set('rows', $record->fresh(['lines'])->lines->mapWithKeys(fn ($l) => [$l->id => ['accepted' => $l->outstanding, 'damaged_replace' => 0, 'damaged_short' => 0, 'short_closed' => 0, 'cost' => Pricing::decimal($l->unit_cost_minor)]])->all());
                    $set('token', '');
                })]),
                ViewEntry::make('receiving_table')->view('filament.purchase-receiving')->viewData(function ($record, Get $get) use ($correction): array {
                    $order = $correction ? $record->order : $record;

                    return ['order' => $order->fresh(['lines']), 'rows' => $get('rows') ?? [], 'correction' => $correction];
                })->columnSpanFull(),
                TextInput::make('delivery_reference')->label('Supplier delivery-note reference (optional)')->maxLength(255),
                Textarea::make('reason')->label($correction ? 'Correction reason' : 'Receipt note (optional)')->required($correction)->maxLength(2000),
                Actions::make([Action::make('previewReceipt')->label('Preview quantities before posting')->action(function ($record, Get $get, Set $set) use ($correction): void {
                    $order = $correction ? $record->order : $record;
                    CommerceAction::run(function () use ($record, $order, $get, $set, $correction): void {
                        $plan = app(PurchaseReceiving::class)->preview($order->id, $get('baseline'), $get('rows') ?? [], $correction ? $record->id : null);
                        $set('token', $plan['token']);
                        $set('preview', collect($plan['rows'])->map(fn ($row, $id) => $order->lines->firstWhere('id', $id)->sku.': accepted '.sprintf('%+d', $row['accepted']).', damaged/replacement '.$row['damaged_replace'].', damaged/shortfall '.$row['damaged_short'].', short-close '.$row['short_closed'])->implode("\n"));
                    });
                })]),
                Textarea::make('preview')->readOnly()->dehydrated(false)->rows(5),
            ])->modalSubmitActionLabel($correction ? 'Post linked correction' : 'Post previewed receipt')->action(function ($record, array $data) use ($correction): void {
                $order = $correction ? $record->order : $record;
                CommerceAction::run(fn () => app(PurchaseReceiving::class)->post($order->id, $data['key'], $data['token'] ?? '', $data['rows'], $data['delivery_reference'] ?? null, $data['reason'] ?? null, $correction ? $record->id : null));
                $record->refresh();
                Notification::make()->success()->title($correction ? 'Correction posted; original receipt retained' : 'Receipt posted; accepted stock refreshed')->send();
            });
    }
}
