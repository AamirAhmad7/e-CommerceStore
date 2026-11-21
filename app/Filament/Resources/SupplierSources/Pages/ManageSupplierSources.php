<?php

namespace App\Filament\Resources\SupplierSources\Pages;

use App\Filament\Resources\SupplierSources\SupplierSourceResource;
use App\Services\Purchasing;
use App\Support\CommerceAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Livewire\Attributes\Url;

class ManageSupplierSources extends ManageRecords
{
    protected static string $resource = SupplierSourceResource::class;

    #[Url]
    public ?string $supplier = null;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add supplier SKU')->fillForm(fn () => ['supplier_id' => $this->supplier, 'pack_size' => 1, 'minimum_quantity' => 1, 'is_preferred' => false])->using(fn (array $data) => CommerceAction::run(fn () => app(Purchasing::class)->source($data)))];
    }
}
