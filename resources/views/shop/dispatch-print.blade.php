<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow, noarchive"><title>{{ $title }} | {{ $brand }}</title>@include('shop.print-styles')</head>
<body><main>
<div class="print-controls"><button type="button" onclick="window.print()">Print</button> <a href="{{ App\Filament\Pages\Dispatch::getUrl() }}">Back to Dispatch</a><p>Printing changes no inventory, order state or notification.</p></div>
<h1>{{ $brand }}</h1><h2>{{ $title }}</h2>
@if($manifest)
    <p>Handover recorded {{ $manifest->created_at }} by staff #{{ $manifest->actor_id }}.</p>
    @foreach($manifest->shipments->groupBy('courier_id') as $parcels)
        <h2>{{ $parcels->first()->courier_name }} {{ $parcels->first()->service_name }}</h2>
        <table><thead><tr><th>Order</th><th>Tracking number</th><th>City</th><th>Collect PKR</th></tr></thead><tbody>
        @foreach($parcels as $parcel)<tr><td>{{ $parcel->order->number }}</td><td>{{ $parcel->tracking_number }}</td><td>{{ $parcel->order->customer['city'] }}</td><td>{{ App\Services\Pricing::format($parcel->expected_cod_minor) }}</td></tr>@endforeach
        </tbody></table><p>Parcels: {{ $parcels->count() }}. Total COD: {{ App\Services\Pricing::format($parcels->sum('expected_cod_minor')) }}.</p>
    @endforeach
    <p><strong>Total parcels: {{ $manifest->shipments->count() }}. Total COD: {{ App\Services\Pricing::format($manifest->shipments->sum('expected_cod_minor')) }}.</strong></p>
@else
    <table><thead><tr><th>SKU</th><th>Product / color / size</th><th>Total units</th><th>Separate into orders</th></tr></thead><tbody>
    @foreach($items as $sku => $item)<tr><td>{{ $sku }}</td><td>{{ $item['product'] }} / {{ $item['color'] }} / {{ $item['size'] }}</td><td>{{ $item['quantity'] }}</td><td>{{ implode('; ', $item['orders']) }}</td></tr>@endforeach
    </tbody></table>
@endif
</main></body></html>
