<?php

namespace App\Support;

use App\Models\Order;
use App\Services\OrderCare;
use App\Services\OrderMessages;
use App\Services\Pricing;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class OrderCareActions
{
    private static function run(Order $order, callable $callback): void
    {
        Gate::authorize('update', $order);
        CommerceAction::run($callback);
        $order->refresh();
        Notification::make()->title('Order updated')->success()->send();
    }

    public static function schema(Order $record): array
    {
        $refunded = (int) DB::table('order_refunds')->where('order_id', $record->id)->sum('amount_minor');
        $components = [TextEntry::make('funds_summary')->label('Collected / refunded')->state(Pricing::format($record->collected_minor).' / '.Pricing::format($refunded))];
        $components[] = Actions::make([
            Action::make('rejectTransfer')->label('Reject transfer proof')->visible(fn () => $record->payment_status === 'proof_submitted' && in_array($record->status, ['pending', 'confirmed'], true) && Gate::allows('verify-payments'))->schema(self::decisionFields())
                ->after(fn ($livewire) => $livewire->refreshOrderCare())->action(function (array $data) use ($record): void {
                    Gate::authorize('verify-payments');
                    self::run($record, fn () => app(OrderCare::class)->rejectTransfer($record->id, auth()->id(), $data['explanation'], $data['internal_note'] ?? null));
                }),
            Action::make('refund')->label('Record completed manual refund')->visible(fn () => $record->collected_minor > $refunded && (in_array($record->status, ['cancelled', 'returned'], true) || $record->requests()->where('type', 'return')->where('status', 'received')->exists()) && Gate::allows('verify-payments'))
                ->modalDescription('Record money already refunded outside this application. This action never sends money. Use the actual external reference; a repeated reference is not recorded twice.')
                ->schema([TextInput::make('amount')->label('Amount in PKR')->required()->regex('/^\d{1,8}(?:\.\d{1,2})?$/'), TextInput::make('reference')->required()->maxLength(150)])
                ->after(fn ($livewire) => $livewire->refreshOrderCare())->action(function (array $data) use ($record): void {
                    Gate::authorize('verify-payments');
                    self::run($record, fn () => app(OrderCare::class)->refund($record->id, Pricing::parse($data['amount'], 'PKR'), 'PKR', $data['reference'], auth()->id()));
                }),
        ]);
        foreach ($record->requests()->with('lines.orderLine')->orderByDesc('id')->get() as $request) {
            $details = [TextEntry::make('reason_'.$request->id)->label('Customer reason')->state($request->reason), TextEntry::make('decision_'.$request->id)->label('Customer-visible decision')->state($request->explanation ?? 'Awaiting decision'), TextEntry::make('note_'.$request->id)->label('Internal note (never emailed)')->state($request->internal_note ?? 'None'), TextEntry::make('audit_'.$request->id)->label('Decision audit')->state('Staff '.($request->decided_by ?? '-').' / '.($request->decided_at ?? '-').' / receipt '.($request->received_at ?? '-'))];
            $receiptFields = [];
            foreach ($request->lines as $item) {
                $line = $item->orderLine;
                $previous = DB::table('order_request_lines as l')->join('order_requests as r', 'r.id', '=', 'l.order_request_id')->where('l.order_line_id', $line->id)->where('r.id', '!=', $request->id)->whereIn('r.status', ['pending', 'approved', 'received'])->sum('l.quantity');
                $receipt = DB::table('return_receipts')->where('order_request_line_id', $item->id)->first();
                $label = $line->product_name.' / '.$line->color.' / '.$line->size;
                $details[] = TextEntry::make('item_'.$item->id)->label($label)->state('Purchased: '.$line->quantity.'; requested: '.$item->quantity.'; other pending/approved/received: '.$previous.($receipt ? '; received resellable: '.$receipt->resellable.', damaged: '.$receipt->damaged.'; staff '.$receipt->actor_id.' at '.$receipt->created_at : ''));
                $receiptFields[] = TextInput::make('lines.'.$item->id.'.resellable')->label($label.' - resellable (approved '.$item->quantity.')')->integer()->minValue(0)->maxValue($item->quantity)->required()->default(0);
                $receiptFields[] = TextInput::make('lines.'.$item->id.'.damaged')->label($label.' - damaged')->integer()->minValue(0)->maxValue($item->quantity)->required()->default(0);
            }
            $actions = [];
            foreach ([true => 'Approve', false => 'Reject'] as $approve => $label) {
                $actions[] = Action::make(strtolower($label).'Request'.$request->id)->label($label.' request')->visible($request->status === 'pending')->schema(self::decisionFields())
                    ->modalDescription('Rechecks the current order when submitted. Give the customer a clear explanation and next step. Approval alone never records a refund.')
                    ->after(fn ($livewire) => $livewire->refreshOrderCare())->action(function (array $data) use ($record, $request, $approve): void {
                        self::run($record, fn () => app(OrderCare::class)->decide($record->id, $request->id, (bool) $approve, auth()->id(), $data['explanation'], $data['internal_note'] ?? null));
                    });
            }
            $actions[] = Action::make('receiveReturn'.$request->id)->label('Receive and classify returned units')->visible($request->type === 'return' && $request->status === 'approved')->schema([...$receiptFields, Textarea::make('note')->label('Internal receipt note')->maxLength(1000)])
                ->modalDescription('Record only after all approved units in this request physically arrive. Resellable plus damaged must equal each approved quantity. Only resellable units increase stock.')
                ->after(fn ($livewire) => $livewire->refreshOrderCare())->action(function (array $data) use ($record, $request): void {
                    self::run($record, fn () => app(OrderCare::class)->receive($record->id, $request->id, $data['lines'], auth()->id(), $data['note'] ?? null));
                });
            $details[] = Actions::make($actions);
            $components[] = Section::make(ucfirst($request->type).' #'.$request->id.' - '.$request->status)->key('request-'.$request->id)->schema($details)->columnSpanFull();
        }
        foreach (DB::table('order_refunds')->where('order_id', $record->id)->get() as $refund) {
            $components[] = TextEntry::make('refund_'.$refund->id)->label('Completed refund')->state(Pricing::format($refund->amount_minor, $refund->currency).' / '.$refund->reference.' / staff '.$refund->actor_id.' / '.$refund->created_at);
        }
        foreach ($record->events()->orderByDesc('id')->get() as $event) {
            $components[] = Section::make($event->subject)->key('event-'.$event->id)->schema([
                TextEntry::make('delivery_'.$event->id)->label('Email delivery')->state($event->sent_at ? 'Sent '.$event->sent_at : ($event->last_error ? 'Failed: '.$event->last_error : 'Queued / pending worker')),
                Actions::make([
                    Action::make('message'.$event->id)->label('Open message preview')->url(route('orders.message', $event))->openUrlInNewTab(),
                    Action::make('retryMessage'.$event->id)->label('Retry unsent email')->visible(! $event->sent_at)->requiresConfirmation()->after(fn ($livewire) => $livewire->refreshOrderCare())->action(function () use ($record, $event): void {
                        self::run($record, fn () => OrderMessages::queue($event));
                    }),
                ]),
            ])->collapsed()->columnSpanFull();
        }

        return $components;
    }

    private static function decisionFields(): array
    {
        return [Textarea::make('explanation')->label('Customer-visible explanation and next step')->required()->maxLength(1000), Textarea::make('internal_note')->label('Internal note (staff only)')->maxLength(1000)];
    }
}
