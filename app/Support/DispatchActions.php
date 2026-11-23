<?php

namespace App\Support;

use App\Models\Courier;
use App\Models\Order;
use App\Services\Dispatching;
use App\Services\Fulfillment;
use App\Services\Pricing;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class DispatchActions
{
    public static function orderActions(): array
    {
        $actions = [
            Action::make('packParcel')->label('Review and mark packed')->visible(fn (Order $record): bool => in_array($record->status, ['pending', 'confirmed', 'processing'], true) && ! $record->shipment)
                ->fillForm(fn (Order $record): array => ['snapshot' => Dispatching::snapshot($record)])
                ->schema(fn (Order $record): array => [Hidden::make('snapshot')->required(), TextEntry::make('eligibility')->state(Dispatching::blocked($record) ?? 'Eligible. Confirm every quantity packed; stock remains reserved.'), ...$record->lines->map(fn ($line) => TextInput::make('quantities.'.$line->id)->label($line->product_name.' / '.$line->color.' / '.$line->size.' / '.$line->sku.' - expected '.$line->quantity)->integer()->required()->minValue(1)->maxValue($line->quantity))->all()])
                ->action(function (Order $record, array $data): void {
                    CommerceAction::run(fn () => app(Dispatching::class)->pack($record->id, $data['snapshot'], $data['quantities']));
                    $record->refresh();
                    Notification::make()->success()->title('Packed; stock remains reserved')->send();
                }),
            Action::make('parcelSlip')->label('Packing slip')->url(fn (Order $record): string => route('orders.packing-slip', $record))->openUrlInNewTab(),
            Action::make('manifest')->label('Print manifest')->visible(fn (Order $record): bool => (bool) $record->shipment?->dispatch_manifest_id)->url(fn (Order $record): string => route('dispatch.manifest', $record->shipment->dispatch_manifest_id))->openUrlInNewTab(),
            Action::make('legacyCourier')->label('Attach legacy courier details')->visible(fn (Order $record): bool => ! $record->shipment && in_array($record->status, ['shipped', 'delivered'], true))
                ->modalDescription('For an order already shipped through the older workflow. This does not ship again or change stock. Existing verified COD collection is carried forward; see the payment history.')
                ->fillForm(fn (Order $record): array => ['snapshot' => Dispatching::snapshot($record)])
                ->schema([Hidden::make('snapshot'), Select::make('courier_id')->options(fn () => Courier::where('is_active', true)->pluck('name', 'id')->all())->required(), TextInput::make('tracking_number')->required()->maxLength(150)])
                ->action(function (Order $record, array $data): void {
                    CommerceAction::run(fn () => app(Dispatching::class)->attachLegacy($record->id, $data['snapshot'], (int) $data['courier_id'], $data['tracking_number']));
                    $record->refresh();
                    Notification::make()->success()->title('Historical courier details attached')->send();
                }),
        ];
        foreach (['issue' => 'Record delivery issue', 'with_courier' => 'Plan reattempt', 'returning' => 'Returning to store', 'delivered' => 'Complete order (delivered)'] as $target => $label) {
            $actions[] = Action::make('parcel_'.$target)->label($label)->visible(fn (Order $record): bool => $record->status === 'shipped' && in_array($record->shipment?->stage, $target === 'with_courier' ? ['issue'] : ['with_courier', 'issue'], true))
                ->fillForm(fn (Order $record): array => ['snapshot' => Dispatching::snapshot($record), 'reason' => $target === 'issue' ? null : $label])
                ->schema([Hidden::make('snapshot'), Select::make('reason')->label('Reason')->options(['customer_unavailable' => 'Customer unavailable', 'address_clarification' => 'Address clarification', 'refusal' => 'Refusal', 'other' => 'Other', $label => $label])->required(), Textarea::make('note')->label('Internal note')->maxLength(1000), Textarea::make('explanation')->label('Customer-visible explanation (optional)')->maxLength(1000)])
                ->action(function (Order $record, array $data) use ($target): void {
                    CommerceAction::run(fn () => app(Dispatching::class)->event($record->id, $data['snapshot'], $target, $data['reason'], $data['note'] ?? null, $data['explanation'] ?? null));
                    $record->refresh();
                    Notification::make()->success()->title('Delivery progress updated')->send();
                });
        }
        $actions[] = Action::make('receiveCourierReturn')->label('Received at store')->visible(fn (Order $record): bool => $record->shipment?->stage === 'returning')
            ->modalDescription('Physically count every line. Only resellable units return to available stock. This is a courier return, not a customer return or refund.')
            ->fillForm(fn (Order $record): array => ['snapshot' => Dispatching::snapshot($record)])
            ->schema(fn (Order $record): array => [Hidden::make('snapshot'), ...$record->lines->flatMap(fn ($line) => [TextInput::make('lines.'.$line->id.'.resellable')->label($line->sku.' resellable (total returned '.$line->quantity.')')->integer()->required()->minValue(0)->maxValue($line->quantity)->default(0), TextInput::make('lines.'.$line->id.'.damaged')->label($line->sku.' damaged')->integer()->required()->minValue(0)->maxValue($line->quantity)->default(0)])->all(), Textarea::make('note')->maxLength(1000)])
            ->action(function (Order $record, array $data): void {
                CommerceAction::run(fn () => app(Dispatching::class)->receive($record->id, $data['snapshot'], $data['lines'], $data['note'] ?? null));
                $record->refresh();
                Notification::make()->success()->title('Courier return received; disposition audited')->send();
            });
        $actions[] = Action::make('codCollection')->label('Confirm COD collection')->visible(fn (Order $record): bool => $record->payment_method === 'cod' && $record->status === 'delivered' && $record->payment_status !== 'paid' && Gate::allows('verify-payments'))
            ->modalDescription(fn (Order $record): string => 'Record evidence that the courier collected '.Pricing::format(Dispatching::collectible($record)).' from the customer. Delivery alone is not evidence. This does not record a remittance to the store.')
            ->schema([TextInput::make('reference')->label('Collection evidence/reference')->required()->maxLength(400)])
            ->action(function (Order $record, array $data): void {
                Gate::authorize('verify-payments');
                Gate::authorize('update', $record);
                CommerceAction::run(fn () => app(Fulfillment::class)->verifyPayment($record->id, auth()->id(), $data['reference']));
                $record->refresh();
                Notification::make()->success()->title('Customer collection confirmed; courier remittance remains separate')->send();
            });

        return $actions;
    }

    public static function bulkActions(): array
    {
        return [
            BulkAction::make('pickingList')->label('Print combined picking list')->action(fn ($records, $livewire) => $livewire->redirect(route('dispatch.picking', ['orders' => $records->modelKeys()]))),
            BulkAction::make('handover')->label('Assign to courier')->modalWidth('7xl')
                ->fillForm(fn ($records): array => ['key' => (string) Str::uuid(), 'rows' => $records->map(fn (Order $order): array => ['order_id' => $order->id, 'snapshot' => Dispatching::snapshot($order), 'description' => $order->number.' / '.$order->customer['city'].' / Collect '.Pricing::format(Dispatching::collectible($order)), 'readiness' => Dispatching::blocked($order) ?? ($order->shipment?->stage === 'packed' ? 'Ready for handover' : 'Must be packed first')])->all()])
                ->schema([Hidden::make('key'), Repeater::make('rows')->label('One parcel per order; all rows must be ready')->addable(false)->deletable(false)->reorderable(false)->schema([Hidden::make('order_id'), Hidden::make('snapshot'), TextInput::make('description')->readOnly()->dehydrated(false), TextInput::make('readiness')->readOnly()->dehydrated(false), Select::make('courier_id')->label('Courier')->options(fn () => Courier::where('is_active', true)->get()->mapWithKeys(fn ($c) => [$c->id => $c->name.($c->service_name ? ' / '.$c->service_name : '')])->all())->required(), TextInput::make('tracking_number')->label('Tracking number (text, keep leading zeros)')->required()->maxLength(150)])->columns(2)])
                ->modalSubmitActionLabel('Assign and hand over to courier')
                ->action(function (array $data, $livewire): void {
                    $manifest = null;
                    CommerceAction::run(function () use ($data, &$manifest): void {
                        $manifest = app(Dispatching::class)->handover($data['rows'], $data['key']);
                    });
                    Notification::make()->success()->title('Parcels handed over once')->send();
                    $livewire->redirect(route('dispatch.manifest', $manifest));
                }),
        ];
    }
}
