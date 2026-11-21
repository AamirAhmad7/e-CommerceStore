<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseReceipt;
use App\Support\PurchaseForms;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

class PurchaseReceipts extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.purchase-receipts';

    protected static string|\UnitEnum|null $navigationGroup = 'Purchasing';

    #[Url]
    public ?string $supplier = null;

    #[Url]
    public ?string $purchase = null;

    #[Url]
    public bool $recent = false;

    #[Url]
    public ?string $product = null;

    public static function canAccess(): bool
    {
        return Gate::allows('manage-commerce');
    }

    public function table(Table $table): Table
    {
        return $table->emptyStateHeading('No receipts in this view')->emptyStateDescription('Open an issued purchase order and choose Receive delivery.')->emptyStateActions([Action::make('openPurchases')->label('Open purchase orders')->url(PurchaseOrderResource::getUrl())])->query(fn () => PurchaseReceipt::with(['order', 'actor'])->withSum('lines', 'accepted')->when($this->product, fn ($q) => $q->whereHas('lines.orderLine.variant.productColor', fn ($c) => $c->where('product_id', $this->product)))->when($this->supplier, fn ($q) => $q->whereHas('order', fn ($p) => $p->where('supplier_id', $this->supplier)))->when($this->purchase, fn ($q) => $q->where('purchase_order_id', $this->purchase))->when($this->recent, fn ($q) => $q->where('created_at', '>=', now()->subDays(30))))->columns([TextColumn::make('reference')->searchable(), TextColumn::make('order.reference')->label('Purchase order')->searchable(), TextColumn::make('order.supplier_name')->label('Supplier'), TextColumn::make('kind')->badge(), TextColumn::make('delivery_reference')->label('Delivery note')->searchable(), TextColumn::make('lines_sum_accepted')->label('Accepted stock change'), TextColumn::make('actor.name')->label('Staff'), TextColumn::make('created_at')->dateTime()->timezone('Asia/Karachi')])->defaultSort('id', 'desc')->recordActions([
            Action::make('audit')->label('Open receipt breakdown')->modalSubmitAction(false)->modalWidth('7xl')->modalContent(function (PurchaseReceipt $record) {
                Gate::authorize('manage-commerce');

                return view('filament.purchase-receipt-audit', ['receipt' => $record->load('lines.orderLine')]);
            }),
            PurchaseForms::receiptAction(true)->visible(fn ($record) => $record->correction_of_id === null),
        ])->paginated([10, 25, 50]);
    }
}
