<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Packing slip {{ $order->number }} | {{ $brand }}</title>
    @include('shop.print-styles')
</head>
<body>
<main>
    <div class="print-controls"><button type="button" onclick="window.print()">Print</button><p>Use A4 portrait, 100% scale. In Chrome: More settings: uncheck Headers and footers. Check the preview has no date, title or admin URL.</p></div>
    @include('shop.print-brand')
    <p><strong>Packing slip</strong></p>
    <p>Order reference: <strong>{{ $order->number }}</strong></p>
    <h2>Ship to</h2>
    <address>{{ $order->customer['name'] ?? '' }}
{{ $order->customer['address'] ?? '' }}
{{ collect([$order->customer['city'] ?? null, $order->customer['region'] ?? null, $order->customer['postal_code'] ?? null])->filter()->implode(', ') }}
{{ $order->customer['country'] ?? '' }}</address>
    <table aria-label="Items to pack">
        <thead><tr><th class="product" scope="col">Product</th><th scope="col">Color</th><th scope="col">Size</th><th class="sku" scope="col">SKU</th><th class="quantity" scope="col">Qty</th></tr></thead>
        <tbody>@foreach($order->lines as $line)<tr><td>{{ $line->product_name }}</td><td>{{ $line->color }}</td><td>{{ $line->size }}</td><td>{{ $line->sku }}</td><td class="quantity">{{ $line->quantity }}</td></tr>@endforeach</tbody>
    </table>
    <p>Total units: <strong>{{ $order->lines->sum('quantity') }}</strong></p>
</main>
</body>
</html>
