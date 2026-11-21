<div style="overflow-x:auto">
    @php($stateRoot = Illuminate\Support\Str::beforeLast($getStatePath(), '.'))
    @foreach($product->colors as $color)
        <h3 style="font-weight:600;margin:1rem 0">{{ $color->name }}</h3>
        <table style="width:100%;text-align:left;border-collapse:collapse">
            <caption style="text-align:left">Enter quantities for {{ $color->name }}. Blank rows are skipped.</caption>
            <thead><tr>@foreach(['Size', 'SKU', 'On hand', 'Reserved', 'Available', 'Quantity', 'PKR checkout price'] as $heading)<th scope="col" style="padding:.5rem">{{ $heading }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach($color->variants as $variant)
                @php($stock = App\Services\Inventory::readSnapshot($rows[$variant->id]['snapshot']))
                <tr wire:key="stock-entry-{{ $variant->id }}" @if((int)$focus === $variant->id) style="outline:2px solid currentColor" @endif>
                    <th scope="row" style="padding:.5rem">{{ $variant->size }}</th>
                    <td style="padding:.5rem">{{ $variant->sku }}</td>
                    <td style="padding:.5rem">{{ $stock['on_hand'] ?? 'Not configured' }}</td>
                    <td style="padding:.5rem">{{ $stock['reserved'] }}</td>
                    <td style="padding:.5rem">{{ max(0, (int)$stock['on_hand'] - $stock['reserved']) }}</td>
                    <td style="padding:.5rem"><input type="number" min="0" max="1000000" step="1" style="width:7rem;border:1px solid currentColor;border-radius:.25rem;padding:.4rem" aria-label="Quantity for {{ $color->name }} / {{ $variant->size }} / {{ $variant->sku }}" wire:model.live.debounce.300ms="{{ $stateRoot }}.rows.{{ $variant->id }}.quantity" @if((int)$focus === $variant->id) autofocus @endif></td>
                    <td style="padding:.5rem">
                        @if(App\Services\Pricing::resolve($variant, 'PKR', $product) > 0)
                            {{ $variant->pkr_price_minor === null ? 'Inherited product price' : 'Variant price configured' }}
                        @else
                            <button type="button" style="text-decoration:underline" wire:click="replaceMountedAction('managePrices', { variant: {{ $variant->id }} })">Set PKR price</button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endforeach
</div>
