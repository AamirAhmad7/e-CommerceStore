<?php

namespace App\Filament\Resources\Orders;

use App\Models\Order;
use App\Models\StockMovement;
use App\Services\Fulfillment;
use App\Services\Pricing;
use App\Services\Promotions;
use App\Support\OrderCareActions;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class OrderResource extends Resource
{
    protected static string|\UnitEnum|null $navigationGroup = 'Orders & customers';

    protected static ?string $model = Order::class;

    protected static ?string $recordTitleAttribute = 'number';

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with('shipment'))->columns([
            TextColumn::make('number')->formatStateUsing(fn (Order $record): string => 'Order #'.$record->id)->description(fn (Order $record): string => $record->number)->wrap()->searchable(), TextColumn::make('customer.phone')->label('Phone')->searchable(), TextColumn::make('shipment.tracking_number')->label('Tracking')->searchable()->toggleable(isToggledHiddenByDefault: true), TextColumn::make('customer.name')->label('Customer')->searchable(query: fn ($query, string $search) => $query->where('customer->name', 'like', '%'.$search.'%')),
            ...(\Illuminate\Support\Facades\Schema::hasTable('assisted_sales') ? [TextColumn::make('assistedSale.source')->label('Acquisition source')->placeholder('Storefront')] : []),
            TextColumn::make('status')->label('Order stage')->formatStateUsing(fn (Order $record): string => $record->stageLabel())->badge(), TextColumn::make('payment_method')->formatStateUsing(fn (string $state): string => $state === 'cod' ? 'COD' : ucwords(str_replace('_', ' ', $state))), TextColumn::make('payment_status')->badge(),
            TextColumn::make('total_minor')->label('Total')->formatStateUsing(fn ($state) => Pricing::format((int) $state)), TextColumn::make('created_at')->dateTime()->sortable(),
        ])->defaultSort('id', 'desc')->filters([
            Filter::make('address_requests')->label('Address corrections')->query(fn ($query) => $query->whereHas('requests', fn ($r) => $r->where('type', 'address')->where('status', 'pending'))),
            Filter::make('failed_messages')->label('Failed customer emails')->query(fn ($query) => $query->whereHas('events', fn ($e) => $e->whereNull('sent_at')->whereNotNull('last_error')->where('recipient', '!=', ''))),
            Filter::make('waiting_messages')->label('Emails waiting over 15 minutes')->query(fn ($query) => $query->whereHas('events', fn ($e) => $e->whereNull('sent_at')->whereNull('last_error')->where('recipient', '!=', '')->where('created_at', '<', now()->subMinutes(15)))),
            Filter::make('pending_cancellations')->label('Cancellation requests')->query(fn ($query) => $query->whereHas('requests', fn ($requests) => $requests->where('type', 'cancellation')->where('status', 'pending'))),
            Filter::make('pending_returns')->label('Pending returns')->query(fn ($query) => $query->whereHas('requests', fn ($requests) => $requests->where('type', 'return')->where('status', 'pending'))),
            Filter::make('needs_attention')->label('Needs attention')->query(fn ($query) => $query->where(function ($q): void {
                $q->where('payment_status', 'proof_submitted')->orWhereHas('requests', fn ($r) => $r->where('status', 'pending')->orWhere(fn ($r) => $r->where('type', 'return')->where('status', 'approved')))->orWhereHas('events', fn ($e) => $e->whereNull('sent_at')->whereNotNull('last_error'));
            })),
            SelectFilter::make('status')->options(array_combine(array_keys(Fulfillment::NEXT), array_keys(Fulfillment::NEXT))),
            SelectFilter::make('payment_status')->options(['unpaid' => 'Unpaid', 'proof_submitted' => 'Proof submitted', 'proof_rejected' => 'Proof rejected', 'paid' => 'Paid']),
        ])->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Customer requests, returns and messages')->schema(fn (Order $record) => OrderCareActions::schema($record))->columnSpanFull(),
            Section::make('Dispatch and courier ledger')->schema([ViewEntry::make('dispatch')->view('filament.dispatch-order')->viewData(fn (Order $record): array => ['order' => $record->fresh(['shipment.settlementLine'])])])->columnSpanFull(),
            Section::make('Order and delivery')->schema(array_map(fn ($field) => $field === 'status' ? TextEntry::make($field)->label('Order stage')->formatStateUsing(fn (Order $record): string => $record->stageLabel()) : ($field === 'payment_method' ? TextEntry::make($field)->formatStateUsing(fn (string $state): string => $state === 'cod' ? 'COD' : ucwords(str_replace('_', ' ', $state))) : TextEntry::make($field)), ['number', ...(\Illuminate\Support\Facades\Schema::hasTable('assisted_sales') ? ['assistedSale.reference', 'assistedSale.source'] : []), 'status', 'payment_method', 'payment_status', 'customer.name', 'customer.email', 'customer.phone', 'customer.address', 'customer.city', 'customer.region', 'customer.postal_code', 'customer.country', 'customer.order_notes', 'shipping_name', 'expires_at']))->columns(2),
            ViewEntry::make('promotion_receipt')->view('shop.order-promotion')->viewData(fn ($record): array => ['order' => $record])->columnSpanFull(),
            TextEntry::make('promotion_refund_remaining')->label('Remaining paid-value refund limit')->state(fn ($record) => $record->promotion_snapshot ? Pricing::format(max(0, min($record->collected_minor, Promotions::refundLimit($record)) - (int) DB::table('order_refunds')->where('order_id', $record->id)->sum('amount_minor'))) : 'Existing collection limit applies'),
            Section::make('PKR totals')->schema(array_map(fn ($field) => TextEntry::make($field.'_minor')->label(ucfirst($field))->formatStateUsing(fn ($state) => Pricing::format((int) $state)), ['subtotal', 'discount', 'tax', 'shipping', 'total'])),
            RepeatableEntry::make('lines')->schema([TextEntry::make('product_name'), TextEntry::make('sku'), TextEntry::make('color'), TextEntry::make('size'), TextEntry::make('quantity'), TextEntry::make('unit_price_minor')->formatStateUsing(fn ($state) => Pricing::format((int) $state)), TextEntry::make('total_minor')->formatStateUsing(fn ($state) => Pricing::format((int) $state))])->columns(3)->columnSpanFull(),
            RepeatableEntry::make('histories')->schema([TextEntry::make('created_at')->dateTime(), TextEntry::make('status'), TextEntry::make('payment_status'), TextEntry::make('actor_id'), TextEntry::make('note')])->columnSpanFull(),
            Section::make('Order stock movements')->schema([
                ViewEntry::make('stock_audit')->view('filament.stock-history')->viewData(fn (Order $record) => ['movements' => StockMovement::where('order_id', $record->id)->latest('id')->limit(100)->get()]),
            ])->collapsed()->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOrders::route('/'), 'view' => Pages\ViewOrder::route('/{record}')];
    }
}
