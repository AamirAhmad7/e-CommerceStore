<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Pages\Dispatch;
use App\Filament\Resources\Orders\OrderResource;
use App\Services\Fulfillment;
use App\Support\CommerceAction;
use App\Support\DispatchActions;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function refreshOrderCare(): void
    {
        Gate::authorize('view', $this->record);
        $this->record->refresh();
        OrderResource::infolist($this->getSchema('infolist'));
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            ...DispatchActions::orderActions(),
            Action::make('dispatchWorkspace')->label('Open Dispatch')->url(fn (): string => Dispatch::getUrl(['stage' => 'all', 'tableSearch' => $this->record->number])),
            Action::make('printPackingSlip')->label('Print packing slip')
                ->visible(fn (): bool => Gate::allows('view', $this->record))
                ->url(fn (): string => route('orders.packing-slip', $this->record))
                ->openUrlInNewTab(),
        ];
        foreach (['confirmed' => 'Confirm', 'processing' => 'Process', 'shipped' => 'Ship', 'delivered' => 'Deliver', 'cancelled' => 'Cancel'] as $status => $label) {
            $actions[] = Action::make($status)->label($label)->requiresConfirmation()
                ->visible(fn (): bool => $status !== 'shipped' && ! ($status === 'delivered' && $this->record->shipment) && in_array($status, Fulfillment::NEXT[$this->record->status] ?? [], true)
                    && (! in_array($status, ['processing', 'shipped'], true) || $this->record->payment_method !== 'bank' || $this->record->payment_status === 'paid')
                    && ($status !== 'cancelled' || ! $this->record->requests()->where('type', 'cancellation')->where('status', 'pending')->exists()))
                ->action(function () use ($status): void {
                    Gate::authorize('update', $this->record);
                    CommerceAction::run(fn () => app(Fulfillment::class)->transition($this->record->id, $status, auth()->id()));
                    $this->refreshOrderCare();
                    Notification::make()->title('Order updated')->success()->send();
                })
                ->successNotificationTitle('Order updated');
        }
        $actions[] = Action::make('proof')->label('Download private proof')->url(fn () => route('orders.proof.download', $this->record))->visible(fn () => filled($this->record->proof_path) && Gate::allows('verify-payments'))->openUrlInNewTab();
        $actions[] = Action::make('verifyPayment')->label('Record verified funds')->schema([TextInput::make('reference')->label('Bank receipt or COD collection reference')->required()->maxLength(400)])
            ->modalDescription('Only confirm after independently verifying receipt of funds. An uploaded image alone is not verification.')
            ->visible(fn () => ! ($this->record->payment_method === 'cod' && $this->record->shipment) && $this->record->payment_status !== 'paid' && ! in_array($this->record->status, ['cancelled', 'expired'], true) && ($this->record->payment_method !== 'cod' || $this->record->status === 'delivered') && Gate::allows('verify-payments'))
            ->action(function (array $data): void {
                Gate::authorize('verify-payments');
                Gate::authorize('update', $this->record);
                CommerceAction::run(fn () => app(Fulfillment::class)->verifyPayment($this->record->id, auth()->id(), $data['reference']));
                $this->refreshOrderCare();
                Notification::make()->title('Receipt of funds recorded')->success()->send();
            })
            ->successNotificationTitle('Receipt of funds recorded');

        return $actions;
    }
}
