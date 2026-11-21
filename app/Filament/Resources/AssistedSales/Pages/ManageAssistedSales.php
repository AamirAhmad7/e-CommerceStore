<?php

namespace App\Filament\Resources\AssistedSales\Pages;

use App\Filament\Resources\AssistedSales\AssistedSaleResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAssistedSales extends ManageRecords
{
    protected static string $resource = AssistedSaleResource::class;

    public function mount(): void
    {
        parent::mount();
        if (request()->boolean('create')) {
            $this->mountAction('create');
        }
    }

    protected function getHeaderActions(): array
    {
        return [AssistedSaleResource::createAction()];
    }
}
