<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Services\Purchasing;
use App\Support\CommerceAction;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Livewire\Attributes\Url;

class ManagePurchaseOrders extends ManageRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    #[Url]
    public ?string $supplier = null;

    #[Url]
    public ?string $product = null;

    #[Url]
    public string $scope = 'awaiting';

    public function mount(): void
    {
        parent::mount();
        if (request()->boolean('create')) {
            $this->mountAction('create');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Create purchase order')->modalWidth('7xl')->fillForm(fn () => ['supplier_id' => $this->supplier, 'lines' => []])->using(fn (array $data) => CommerceAction::run(fn () => app(Purchasing::class)->save($data)))->after(function (): void {
                $this->scope = 'draft';
            })->successNotificationTitle('Draft saved. Review and issue when ready.'),
            Action::make('suppliers')->url(SupplierResource::getUrl())->label('Suppliers'),
            Action::make('export')->label('Export open lines CSV')->url(fn () => route('purchases.export', ['supplier' => $this->supplier, 'scope' => $this->scope, 'product' => $this->product])),
        ];
    }
}
