<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\CourierSettlements;
use App\Services\Dispatching;
use App\Services\Pricing;
use App\Support\DispatchActions;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

class Dispatch extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.dispatch';

    protected static string|\UnitEnum|null $navigationGroup = 'Orders';

    #[Url]
    public string $stage = 'ready';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $until = null;

    #[Url]
    public ?string $courier = null;

    public static function canAccess(): bool
    {
        return Gate::allows('manage-commerce');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['stage', 'from', 'until', 'courier'], true)) {
            $this->resetPage($this->getTablePaginationPageName());
            $this->deselectAllTableRecords();
        }
    }

    private function base(): Builder
    {
        return Order::query()->when($this->from, fn ($q) => $q->whereDate('orders.created_at', '>=', $this->from))->when($this->until, fn ($q) => $q->whereDate('orders.created_at', '<=', $this->until))->when($this->courier, fn ($q) => $q->whereHas('shipment', fn ($s) => $s->where('courier_id', $this->courier)));
    }

    public function summary(): array
    {
        Gate::authorize('manage-commerce');

        return ['awaiting' => $this->base()->whereIn('status', ['pending', 'confirmed', 'processing'])->count(), 'issues' => $this->base()->whereHas('shipment', fn ($q) => $q->where('stage', 'issue'))->count(), 'returning' => $this->base()->whereHas('shipment', fn ($q) => $q->where('stage', 'returning'))->count()] + CourierSettlements::totals($this->courier ? (int) $this->courier : null, $this->from, $this->until);
    }

    public function table(Table $table): Table
    {
        return $table->query(function (): Builder {
            $query = $this->base()->with(['shipment', 'lines'])->withSum('lines', 'quantity');
            if ($this->stage === 'ready') {
                $query->whereIn('status', ['pending', 'confirmed', 'processing'])->whereDoesntHave('shipment');
            } elseif ($this->stage === 'awaiting') {
                $query->whereIn('status', ['pending', 'confirmed', 'processing']);
            } elseif ($this->stage === 'outstanding') {
                $query->whereHas('shipment', fn ($q) => $q->whereIn('id', DB::query()->fromSub(CourierSettlements::ledger(), 'ledger')->where('difference', '>', 0)->select('id')));
            } elseif ($this->stage !== 'all') {
                $query->whereHas('shipment', fn ($q) => $q->where('stage', $this->stage))->whereNotIn('status', ['cancelled', 'expired']);
            }

            return $query;
        })->columns([
            TextColumn::make('number')->label('Order')->searchable()->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record->id])),
            TextColumn::make('customer.city')->label('City'), TextColumn::make('lines_sum_quantity')->label('Units'), TextColumn::make('payment_method'),
            TextColumn::make('dispatch_status')->label('Dispatch status')->badge(),
            TextColumn::make('collectible')->label('Amount to collect')->state(fn (Order $record): string => Pricing::format($record->shipment && $record->shipment->stage !== 'packed' ? $record->shipment->expected_cod_minor : Dispatching::collectible($record))),
            TextColumn::make('shipment.collected_minor')->label('Confirmed COD collected')->formatStateUsing(fn ($state): string => Pricing::format((int) $state))->placeholder('Not recorded'),
            TextColumn::make('shipment.tracking_number')->label('Tracking')->searchable(),
            TextColumn::make('eligibility')->label('Next step / blocked reason')->state(fn (Order $record): string => in_array($record->status, ['pending', 'confirmed', 'processing'], true) ? (Dispatching::blocked($record) ?? ($record->shipment ? 'Select parcel for courier handover' : 'Review quantities and mark packed')) : ($record->shipment ? 'Use parcel actions below' : 'Attach legacy courier details')),
        ])->defaultSort('id')->recordActions(DispatchActions::orderActions())->toolbarActions(DispatchActions::bulkActions())->selectCurrentPageOnly()->paginated([10, 25, 50]);
    }
}
