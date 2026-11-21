<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\AssistedSales\AssistedSaleResource;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assistedSale')->label('Create assisted sale')->visible(fn (): bool => Gate::allows('manage-commerce'))->url(fn (): string => AssistedSaleResource::getUrl('index', ['create' => 1])),
            Action::make('assistedSales')->label('Assisted sales follow-up')->visible(fn (): bool => Gate::allows('manage-commerce'))->url(fn (): string => AssistedSaleResource::getUrl('index')),
        ];
    }
}
