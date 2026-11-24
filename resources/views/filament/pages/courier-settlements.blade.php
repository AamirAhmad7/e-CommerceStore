<x-filament-panels::page>
    @if($orderFrom || $orderUntil)<p>Order creation date filter from Dispatch: {{ $orderFrom ?: 'any' }} through {{ $orderUntil ?: 'any' }}.</p>@endif
    <div class="sf-admin-toolbar">
        <label>Payout date from <input type="date" wire:model.live="from"></label><label>Through <input type="date" wire:model.live="until"></label>
        <label>Courier <select wire:model.live="courier"><option value="">All</option>@foreach(App\Models\Courier::orderBy('name')->get() as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
        <label><input type="checkbox" wire:model.live="differences">Unresolved differences only</label>
    </div>
    @php($totals = App\Services\CourierSettlements::totals($courier ? (int)$courier : null, $from, $until, true, $orderFrom, $orderUntil))
    <p><strong>Unresolved posted statement differences: {{ App\Services\Pricing::format($totals['unresolved']) }}</strong>. This sums the larger absolute net or gross discrepancy per parcel after corrections. Positive net difference means short payment; negative means excess payment.</p>
    <p>Confirmed customer collection, courier fees, and actual net remittance are separate. Posting or adjusting this ledger never changes order totals, customer refunds or inventory. For unposted collections, open <a href="{{ App\Filament\Pages\Dispatch::getUrl(['stage' => 'outstanding', 'courier' => $courier]) }}">Dispatch: confirmed COD awaiting remittance</a>.</p>
    {{ $this->table }}
</x-filament-panels::page>
