<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductVariant;
use App\Models\RestockSubscription;
use App\Services\Inventory;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class RestockRequests extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.restock-requests';

    protected static string|\UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Restock requests';

    public static function canAccess(): bool
    {
        return Gate::allows('manage-commerce');
    }

    public function table(Table $table): Table
    {
        $demand = RestockSubscription::waiting()->selectRaw('product_variant_id, COUNT(*) waiting_count, MIN(confirmed_at) oldest_waiting')->groupBy('product_variant_id');

        return $table->query(ProductVariant::query()->joinSub($demand, 'demand', fn ($join) => $join->on('product_variants.id', '=', 'demand.product_variant_id'))->select('product_variants.*', 'demand.waiting_count', 'demand.oldest_waiting')->selectRaw(Inventory::availableSql().' available_now')->with('productColor.product'))
            ->columns([
                TextColumn::make('productColor.product.name')->label('Product')->wrap()->placeholder('Unpublished or removed'),
                TextColumn::make('productColor.name')->label('Color'), TextColumn::make('size'), TextColumn::make('sku')->searchable(),
                TextColumn::make('available_now')->label('Available stock'), TextColumn::make('waiting_count')->label('Confirmed waiting')->sortable(), TextColumn::make('oldest_waiting')->label('Oldest request')->dateTime()->sortable(),
            ])->defaultSort('waiting_count', 'desc')->recordActions([
                Action::make('stock')->label('Open product stock')->url(fn ($record) => $record->productColor->product ? ProductResource::getUrl('edit', ['record' => $record->productColor->product]) : null),
                Action::make('replenish')->label('Replenishment')->url(fn ($record) => Replenishment::getUrl(['product' => $record->productColor->product_id, 'showAll' => true])),
            ])->emptyStateHeading('No confirmed requests waiting')->emptyStateDescription('Only confirmed, unfinished size alerts count here.')->paginated([10, 25, 50]);
    }
}
