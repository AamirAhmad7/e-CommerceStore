<x-filament-panels::page>
    <div class="sf-admin-toolbar">
        <label>Order date from <input type="date" wire:model.live="from"></label>
        <label>Through <input type="date" wire:model.live="until"></label>
        <label>Courier <select wire:model.live="courier"><option value="">All couriers / unassigned</option>@foreach(App\Models\Courier::orderBy('name')->get() as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
    </div>
    @php($summary = $this->summary())
    <div class="sf-dispatch-kpis">
        @foreach(['awaiting' => ['awaiting', 'Parcels awaiting dispatch (including blocked)'], 'issues' => ['issue', 'Delivery issues'], 'returning' => ['returning', 'Returns awaiting physical receipt'], 'outstanding' => ['outstanding', 'Confirmed COD awaiting remittance after recorded fees']] as $key => [$target, $label])
            <button type="button" wire:click="$set('stage', '{{ $target }}')" class="sf-dispatch-kpi"><strong>{{ $key === 'outstanding' ? App\Services\Pricing::format($summary[$key]) : $summary[$key] }}</strong><br>{{ $label }}</button>
        @endforeach
        <a href="{{ App\Filament\Pages\CourierSettlements::getUrl(['courier' => $courier, 'differences' => 1, 'orderFrom' => $from, 'orderUntil' => $until]) }}" class="sf-dispatch-kpi"><strong>{{ App\Services\Pricing::format($summary['unresolved']) }}</strong><br>Unresolved statement differences</a>
    </div>
    <p>Expected COD in transit is not counted as collected. Monetary figures use verified collection evidence. Date filters above use order creation date.</p>
    <nav aria-label="Dispatch views" class="sf-dispatch-tabs">
        @foreach(['packable' => 'Ready to pack', 'ready' => 'Unpacked (including blocked)', 'packed' => 'Packed', 'with_courier' => 'With courier', 'follow_up' => 'No recorded update for 3 days', 'issue' => 'Delivery issue', 'returning' => 'Returning to store', 'delivered' => 'Completed / record collection', 'returned' => 'Returned', 'all' => 'All / older orders'] as $value => $label)<button type="button" wire:click="$set('stage', '{{ $value }}')" @if($stage === $value) aria-current="page" @endif>{{ $label }}</button>@endforeach
    </nav>
    <p>Select packed parcels, then choose Assign to courier. Printing a picking list or slip changes no stock.</p>
    {{ $this->table }}
</x-filament-panels::page>

