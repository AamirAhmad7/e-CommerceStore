<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\Pricing;
use App\Support\OrderMargin;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class OrderMargins extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.order-margins';

    protected static string|\UnitEnum|null $navigationGroup = 'Analytics';

    protected static ?string $navigationLabel = 'Order contribution';

    private array $marginCache = [];

    public static function canAccess(): bool
    {
        return Gate::allows('verify-payments');
    }

    private function margin(Order $order): array
    {
        return $this->marginCache[$order->id] ??= OrderMargin::calculate($order);
    }

    public function table(Table $table): Table
    {
        return $table->query(Order::with(['lines', 'shipment.settlementLine'])->whereIn('status', ['delivered', 'returned']))->columns([
            TextColumn::make('number')->label('Order')->searchable()->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
            TextColumn::make('created_at')->label('Ordered')->date()->sortable(),
            TextColumn::make('collected_minor')->label('Customer collection')->formatStateUsing(fn ($state): string => Pricing::format((int) $state)),
            ...array_map(fn (string $key) => TextColumn::make($key)->label(ucfirst($key))->state(fn (Order $record): string => $this->margin($record)[$key] === null ? 'Not available' : Pricing::format($this->margin($record)[$key])), ['cost', 'fees', 'refunds', 'contribution']),
            TextColumn::make('calculation_note')->label('Completeness')->state(fn (Order $record): string => $this->margin($record)['note'])->wrap(),
        ])->filters([
            Filter::make('last_30_days')->label('Ordered in last 30 days')->query(fn ($query) => $query->where('created_at', '>=', now()->subDays(30))),
        ])->defaultSort('id', 'desc')->paginated([10, 25]);
    }
}
