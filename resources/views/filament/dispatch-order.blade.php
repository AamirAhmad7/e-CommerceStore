<div>
    <p>Dispatch: <strong>{{ $order->dispatch_status }}</strong>.</p>
    @if($parcel = $order->shipment)
        <p>{{ $parcel->courier_name ?: 'Courier not assigned' }} / {{ $parcel->service_name }} / {{ $parcel->tracking_number ?: 'Tracking not assigned' }}</p>
        <p>Expected courier COD: {{ App\Services\Pricing::format($parcel->stage === 'packed' ? App\Services\Dispatching::collectible($order) : $parcel->expected_cod_minor) }}. Confirmed customer collection: {{ App\Services\Pricing::format($parcel->collected_minor) }}.</p>
        @if($line = $parcel->settlementLine)
            @php($balance = $line->balances())
            <p>Courier deductions: {{ App\Services\Pricing::format($balance['fee']) }}. Net remitted: {{ App\Services\Pricing::format($balance['net']) }}. Unresolved net: {{ App\Services\Pricing::format($balance['difference']) }}.</p>
            <a href="{{ App\Filament\Pages\CourierSettlements::getUrl(['tableSearch' => $parcel->tracking_number]) }}">Open settlement and corrections</a>
        @else<p>No courier remittance recorded.</p>@endif
        @if($parcel->received_at)
            <p>Physically received at store: {{ $parcel->received_at }}.</p>
            @foreach(Illuminate\Support\Facades\DB::table('courier_return_receipts')->join('order_lines', 'order_lines.id', '=', 'courier_return_receipts.order_line_id')->where('shipment_id', $parcel->id)->select('order_lines.sku', 'courier_return_receipts.*')->get() as $receipt)
                <p>{{ $receipt->sku }}: resellable {{ $receipt->resellable }}, damaged {{ $receipt->damaged }}. Staff #{{ $receipt->actor_id }}. {{ $receipt->note }}</p>
            @endforeach
        @endif
    @else<p>No courier data yet. Older shipped orders can attach tracking without repeating shipment or stock deduction.</p>@endif
</div>
