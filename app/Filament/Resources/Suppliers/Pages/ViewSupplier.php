<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\SupplierSources\SupplierSourceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('createPo')->label('Create purchase order')->url(fn () => PurchaseOrderResource::getUrl('index', ['supplier' => $this->record->id, 'create' => 1])), Action::make('sourcing')->label('Manage supplied SKUs and quotes')->url(fn () => SupplierSourceResource::getUrl('index', ['supplier' => $this->record->id]))];
    }
}
