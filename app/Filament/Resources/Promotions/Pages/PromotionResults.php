<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Promotions\PromotionResource;
use App\Http\Controllers\PromotionController;
use App\Models\Order;
use App\Services\Pricing;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class PromotionResults extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = PromotionResource::class;

    protected string $view = 'filament.promotion-results';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    public function applyDates(): void
    {
        $this->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d'.($this->from ? '|after_or_equal:from' : '')]);
        $this->resetPage();
    }

    public function resultQuery(): Builder
    {
        $this->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d'.($this->from ? '|after_or_equal:from' : '')]);

        return PromotionController::results($this->getRecord(), $this->from, $this->to);
    }

    public function summary(): array
    {
        $base = $this->resultQuery();
        $totals = (clone $base)->selectRaw('COUNT(*) orders, COALESCE(SUM(r.eligible_units),0) units, COALESCE(SUM(r.eligible_minor),0) merchandise, COALESCE(SUM(r.merchandise_discount_minor),0) discount, COALESCE(SUM(r.shipping_discount_minor),0) shipping')->first();
        $cancelled = (clone $base)->whereIn('o.status', ['cancelled', 'expired'])->count();
        $refunds = DB::table('order_refunds')->whereIn('order_id', (clone $base)->select('o.id'))->sum('amount_minor');

        return ['orders' => $totals->orders, 'eligible units' => $totals->units, 'eligible merchandise before discount' => Pricing::format((int) $totals->merchandise), 'merchandise discount' => Pricing::format((int) $totals->discount), 'shipping discount' => Pricing::format((int) $totals->shipping), 'eligible merchandise after discount' => Pricing::format((int) $totals->merchandise - (int) $totals->discount), 'cancelled / expired orders (included above)' => $cancelled, 'recorded refunds (separate, not netted above)' => Pricing::format((int) $refunds)];
    }

    public function table(Table $table): Table
    {
        return $table->query(fn () => Order::whereIn('id', $this->resultQuery()->select('o.id')))->columns([TextColumn::make('number')->label('Order')->searchable()->url(fn ($record) => OrderResource::getUrl('view', ['record' => $record])), TextColumn::make('created_at')->dateTime()->timezone(config('store.timezone')), TextColumn::make('status')->badge(), TextColumn::make('promotion_snapshot.code')->label('Saved code')->placeholder('Automatic'), TextColumn::make('promotion_snapshot.eligible_minor')->label('Eligible merchandise')->formatStateUsing(fn ($state) => Pricing::format((int) $state)), TextColumn::make('discount_minor')->label('Merchandise discount')->formatStateUsing(fn ($state) => Pricing::format((int) $state)), TextColumn::make('promotion_snapshot.shipping_discount_minor')->label('Shipping discount')->formatStateUsing(fn ($state) => Pricing::format((int) $state))])->defaultSort('id', 'desc')->paginated([10, 25, 50]);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('back')->label('Edit / preview / share')->url(PromotionResource::getUrl()), Action::make('export')->label('Export filtered orders CSV')->url(fn () => route('promotions.export', ['promotion' => $this->getRecord(), 'from' => $this->from, 'to' => $this->to]))];
    }
}
