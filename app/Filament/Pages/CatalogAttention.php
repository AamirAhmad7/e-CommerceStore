<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductVariant;
use App\Services\Inventory;
use App\Support\ProductWorkspace;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

class CatalogAttention extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.catalog-attention';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Sizes needing attention';

    #[Url]
    public string $issue = 'stock';

    public static function canAccess(): bool
    {
        return Gate::allows('manage-commerce');
    }

    public function updatedIssue(): void
    {
        $this->resetPage($this->getTablePaginationPageName());
    }

    public function table(Table $table): Table
    {
        return $table->query(fn () => ProductWorkspace::attention($this->issue)->with('productColor.product'))
            ->columns([
                TextColumn::make('productColor.product.name')->label('Product')->searchable(),
                TextColumn::make('productColor.name')->label('Color'),
                TextColumn::make('size'), TextColumn::make('sku')->searchable(),
                TextColumn::make('on_hand')->label('Physical')->placeholder('Not configured'),
                TextColumn::make('reserved')->label('Reserved'),
                TextColumn::make('available')->state(fn (ProductVariant $record): int => Inventory::available($record)),
                TextColumn::make('price_source')->label('PKR price source')->state(fn (ProductVariant $record): string => $record->pkr_price_minor === null ? 'Product base price' : 'Size override'),
            ])->recordActions([
                Action::make('fix')->label(fn (): string => in_array($this->issue, ['price', 'missing_price'], true) ? 'Set PKR price' : 'Enter stock')
                    ->url(fn (ProductVariant $record): string => ProductResource::getUrl('edit', ['record' => $record->productColor->product_id, 'action' => in_array($this->issue, ['price', 'missing_price'], true) ? 'managePrices' : 'manageStock', 'actionArguments' => ['variant' => $record->id]])),
            ])->defaultSort('product_variants.id')->paginated([10, 25, 50]);
    }
}
