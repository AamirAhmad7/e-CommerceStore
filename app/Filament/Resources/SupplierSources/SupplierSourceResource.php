<?php

namespace App\Filament\Resources\SupplierSources;

use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\SupplierSource;
use App\Services\Pricing;
use App\Services\Purchasing;
use App\Support\CommerceAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SupplierSourceResource extends Resource
{
    protected static ?string $model = SupplierSource::class;

    protected static ?string $navigationLabel = 'Supplier SKUs & quotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Purchasing';

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-commerce');
    }

    public static function canView(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function sourceQuery(): Builder
    {
        $last = DB::table('purchase_receipt_lines as rl')->join('purchase_receipts as r', 'r.id', '=', 'rl.purchase_receipt_id')->join('purchase_order_lines as pl', 'pl.id', '=', 'rl.purchase_order_line_id')->join('purchase_orders as po', 'po.id', '=', 'pl.purchase_order_id')->whereColumn('po.supplier_id', 'supplier_sources.supplier_id')->whereColumn('pl.product_variant_id', 'supplier_sources.product_variant_id')->where('rl.accepted', '>', 0)->orderByDesc('r.id')->limit(1);

        return SupplierSource::with(['supplier', 'variant.productColor.product'])->select('supplier_sources.*')->selectSub((clone $last)->select('rl.unit_cost_minor'), 'last_actual_cost')->selectSub((clone $last)->select('r.created_at'), 'last_actual_at');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('supplier_id')->options(fn () => Supplier::pluck('name', 'id')->all())->required()->searchable()->disabled(fn ($record) => $record !== null)->dehydrated(),
            Select::make('product_variant_id')->label('Store SKU')->required()->searchable()->disabled(fn ($record) => $record !== null)->dehydrated()->getSearchResultsUsing(fn (string $search) => ProductVariant::with('productColor.product')->where(fn ($q) => $q->where('sku', 'like', '%'.$search.'%')->orWhereHas('productColor.product', fn ($p) => $p->where('name', 'like', '%'.$search.'%')))->limit(40)->get()->mapWithKeys(fn ($v) => [$v->id => Purchasing::variantLabel($v)])->all())->getOptionLabelUsing(fn ($value) => ($v = ProductVariant::with('productColor.product')->find($value)) ? Purchasing::variantLabel($v) : null),
            TextInput::make('supplier_sku')->maxLength(255), Toggle::make('is_preferred')->label('Preferred supplier for this store SKU')->default(false), TextInput::make('pack_size')->integer()->minValue(1)->maxValue(100000)->default(1)->required(), TextInput::make('minimum_quantity')->integer()->minValue(1)->maxValue(100000)->default(1)->required(), TextInput::make('lead_days')->integer()->minValue(0)->maxValue(365), TextInput::make('quote')->label('Latest quoted unit cost PKR')->inputMode('decimal')->regex('/^\d{1,8}(?:\.\d{1,2})?$/')->helperText('Blank means unknown. Does not change retail prices or past orders.')]);
    }

    public static function table(Table $table): Table
    {
        return $table->emptyStateHeading('No supplier SKU mappings')->emptyStateDescription('Create a supplier first, then add a mapping with the store SKU and optional quote.')->query(fn ($livewire) => self::sourceQuery()->when($livewire->supplier, fn ($q) => $q->where('supplier_id', $livewire->supplier)))->columns([TextColumn::make('supplier.name')->searchable(), TextColumn::make('variant.sku')->label('Store SKU')->searchable(), TextColumn::make('supplier_sku')->searchable(), TextColumn::make('is_preferred')->label('Preferred')->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'), TextColumn::make('quoted_cost_minor')->label('Latest quote')->formatStateUsing(fn ($state) => Pricing::format($state === null ? null : (int) $state))->placeholder('Unknown'), TextColumn::make('quoted_at')->dateTime(), TextColumn::make('last_actual_cost')->label('Last actual purchase cost')->formatStateUsing(fn ($state) => Pricing::format($state === null ? null : (int) $state))->placeholder('No accepted receipt'), TextColumn::make('last_actual_at')->label('Actual cost date')->dateTime(), TextColumn::make('pack_size'), TextColumn::make('minimum_quantity')])->recordActions([EditAction::make()->mutateRecordDataUsing(fn (array $data, SupplierSource $record) => $data + ['quote' => Pricing::decimal($record->quoted_cost_minor)])->using(fn (array $data, SupplierSource $record) => CommerceAction::run(fn () => app(Purchasing::class)->source($data, $record)))]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSupplierSources::route('/')];
    }
}
