<section>
    <h3 style="font-weight:600">Customer-facing preview · {{ App\Support\StoreSettings::brandName() }}</h3>
    @if($error)<p>{{ $error }}</p>@endif
    @foreach($lines as $line)
        <p style="margin:.75rem 0">{{ $line['product'] }} · {{ $line['color'] }} / {{ $line['size'] }} · {{ $line['sku'] }} × {{ $line['quantity'] }}<br>
        Unit: {{ $line['price'] === null ? 'PKR price unavailable' : App\Services\Pricing::format($line['price']) }} · Line: {{ App\Services\Pricing::format($line['total'] ?? 0) }}
        @if(!$line['valid'])<strong>Unavailable for this quantity (available: {{ $line['available'] }}). Request an updated selection.</strong>@endif</p>
    @endforeach
    <p><strong>Merchandise subtotal: {{ App\Services\Pricing::format($subtotal) }}</strong></p>
    <p>Delivery: {{ $totals ? App\Services\Pricing::format($totals['shipping_minor']).' — '.$totals['shipping_name'] : 'Calculated at checkout when delivery rules are available.' }}</p>
    @if($totals)<p>Tax: {{ App\Services\Pricing::format($totals['tax_minor']) }} · Total: {{ App\Services\Pricing::format($totals['total_minor']) }}</p>@endif
    <p>Stock is confirmed at checkout. Prices and delivery charges are rechecked before confirmation.</p>
</section>
