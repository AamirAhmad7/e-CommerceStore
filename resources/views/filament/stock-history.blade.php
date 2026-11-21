<x-filament::section>
    <p>Most recent 100 movements. Quantities show changes and resulting balances.</p>
    @foreach($movements as $movement)
        <p>{{ $movement->created_at }} | Variant {{ $movement->product_variant_id }} | On hand {{ $movement->on_hand_delta }} / reserved {{ $movement->reserved_delta }} | After {{ $movement->on_hand_after }} / {{ $movement->reserved_after }} | {{ $movement->reason }} | Actor {{ $movement->actor_id ?? 'system/guest' }} | Order {{ $movement->order_id ?? '-' }}</p>
    @endforeach
</x-filament::section>
