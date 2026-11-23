<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive">
<title>{{ ucfirst($document) }} - {{ $order->number }}</title>
@include('shop.print-styles')
<style>
.delivery-label { border:2px solid #111; padding:20px; max-width:160mm; break-inside:avoid; }
.delivery-label address { font-size:18pt; line-height:1.5; margin:16px 0; }
.recipient { font-size:23pt; font-weight:700; }
.sender-address { border-top:2px solid #111; margin-top:20px; padding-top:12px; }
.sender-address address { font-size:12pt; margin:8px 0; }
.cod-amount { border:3px solid #111; padding:14px; margin-top:20px; font-size:28pt; font-weight:800; line-height:1.25; }
.document-warning { border:3px solid #111; padding:12px; font-weight:800; font-size:18pt; margin:12px 0; }
.totals { margin:22px 0 0 auto; max-width:90mm; }
.totals p { display:flex; justify-content:space-between; gap:16px; padding:6px 0; border-bottom:1px solid #bbb; }
</style></head><body><main>
<div class="print-controls"><button type="button" onclick="window.print()">Print {{ $document === 'address' ? 'address label' : ($document === 'products' ? 'product details' : 'invoice') }}</button><p>A4 portrait. Disable browser headers and footers. Use print scaling for smaller label paper.</p></div>
@if($blocked)<p class="document-warning">{{ strtoupper($order->status) }} &mdash; DO NOT DISPATCH OR COLLECT</p>@endif
@if($document === 'address')
<section class="delivery-label"><h1>{{ $brand }}</h1><p>Order: <strong>{{ $order->number }}</strong></p><h2>DELIVER TO</h2>
<address><span class="recipient">{{ $order->customer['name'] ?? '' }}</span>
{{ $order->customer['address'] ?? '' }}
{{ $order->customer['city'] ?? '' }}, {{ $order->customer['region'] ?? '' }} {{ $order->customer['postal_code'] ?? '' }}
{{ ($order->customer['country'] ?? 'PK') === 'PK' ? 'Pakistan' : $order->customer['country'] }}</address>
<p><strong>Phone: {{ $order->customer['phone'] ?? '' }}</strong></p>
@if($order->payment_method === 'cod')<div class="cod-amount">COD TO COLLECT<br>{{ \App\Services\Pricing::format($cod, $order->currency) }}</div>@else<div class="cod-amount">NO COD COLLECTION</div><p>Payment status: {{ str_replace('_', ' ', $order->payment_status) }}</p>@endif
<section class="sender-address"><h2>SENDER / RETURN ADDRESS</h2><strong>{{ $brand }}</strong>
@php($returnAddress = \App\Support\StoreSettings::pages()['address'] ?? '')
@if(filled($returnAddress))<address>{{ $returnAddress }}</address>@else
<p><strong>SAMPLE ADDRESS - REPLACE BEFORE DISPATCH</strong></p>
<address>Unit 12, Example Fashion Building
Sample Road, Sialkot
Punjab, Pakistan</address>
@endif
</section></section>
@else
<h1>{{ $brand }}</h1><h2>{{ $document === 'invoice' ? 'Invoice' : 'Product details / picking list' }}</h2>
<p>Order: <strong>{{ $order->number }}</strong> | {{ $order->created_at->timezone('Asia/Karachi')->format('d M Y') }}</p>
@if($document === 'invoice')<h2>Customer</h2><address>{{ $order->customer['name'] ?? '' }}
{{ $order->customer['address'] ?? '' }}
{{ $order->customer['city'] ?? '' }}, {{ $order->customer['region'] ?? '' }} {{ $order->customer['postal_code'] ?? '' }}</address><p>Phone: {{ $order->customer['phone'] ?? '' }}</p><p>Payment: {{ $order->payment_method === 'cod' ? 'Cash on delivery' : 'Bank transfer' }} / {{ str_replace('_', ' ', $order->payment_status) }}</p>@endif
<h2>Product details</h2><table><thead><tr><th>Product / SKU</th><th>Color / size</th><th>Qty</th>@if($document === 'invoice')<th>Unit price</th><th>Line total</th>@endif</tr></thead><tbody>
@foreach($order->lines as $line)<tr><td>{{ $line->product_name }}<br>{{ $line->sku }}</td><td>{{ $line->color }} / {{ $line->size }}</td><td>{{ $line->quantity }}</td>@if($document === 'invoice')<td>{{ \App\Services\Pricing::format($line->unit_price_minor, $order->currency) }}</td><td>{{ \App\Services\Pricing::format($line->total_minor, $order->currency) }}</td>@endif</tr>@endforeach
</tbody></table><p>Total units: <strong>{{ $order->lines->sum('quantity') }}</strong></p>
@if($document === 'invoice')<div class="totals">@foreach(['subtotal' => 'Subtotal','discount' => 'Discount','shipping' => 'Delivery','tax' => 'Tax','total' => 'Order total'] as $field => $label)@continue($field === 'tax' && !$order->tax_minor)<p><span>{{ $label }}</span><strong>{{ \App\Services\Pricing::format($order->{$field.'_minor'}, $order->currency) }}</strong></p>@endforeach<p><span>Collected</span><strong>{{ \App\Services\Pricing::format($order->collected_minor, $order->currency) }}</strong></p></div><p>Original order amounts. This invoice is not proof of payment; see the payment status above.</p>@endif
@endif
</main></body></html>
