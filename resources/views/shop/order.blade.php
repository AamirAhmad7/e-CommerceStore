@extends('layouts.store')
@section('title','Order '.$order->number.' | '.\App\Support\StoreSettings::brandName())
@section('content')
@php($care = \App\Services\OrderCare::class)
<main id="product" class="product-shell">
<h1 class="page-heading">Your order</h1><p>Thank you. Your order is received. Keep this page to follow confirmation, packing and delivery.</p>@if($support = \App\Support\StoreSettings::whatsappNumber())<p><a class="button-secondary" href="https://wa.me/{{ $support }}?text={{ rawurlencode('Hello, I would like an update on order '.$order->number) }}" target="_blank" rel="noopener">Ask about this order on WhatsApp</a></p>@endif<p>{{ $order->number }}</p>
@php($stages = ['pending' => 'Order received', 'confirmed' => 'Confirmed', 'processing' => 'Preparing your order', 'shipped' => 'With the courier', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'expired' => 'Payment deadline expired', 'returned' => 'Returned'])
<p>Order progress: <strong>{{ $order->stageLabel() }}</strong></p>
<p>Confirmed &rarr; Packed and ready for courier &rarr; Assigned to courier &rarr; Completed (delivered)</p>
@if($order->status === 'pending')<p>We will review your order before preparing it.</p>@endif
@if($order->payment_method === 'cod' && $order->payment_status === 'unpaid')<p>Pay cash when your order arrives. No advance payment is required.</p>@endif
<p>Payment progress: <strong>{{ str_replace('_',' ',$order->payment_status) }}</strong></p>
@if($order->payment_status==='proof_submitted')<p>Your proof was received. Payment is awaiting review.</p>@endif
<p><a href="{{ route('orders.show',$order->access_token) }}">Save this private order link</a>. Do not share it. You can also use Find my order with your order reference and checkout phone or email.</p>
<div class="checkout-grid"><section><h2>Your purchased items</h2>
@foreach($order->lines as $line)<p>{{ $line->product_name }} / {{ $line->color }} / {{ $line->size }} / {{ $line->sku }} x {{ $line->quantity }}<br>{{ \App\Services\Pricing::format($line->unit_price_minor,$line->currency) }} each; {{ \App\Services\Pricing::format($line->total_minor,$line->currency) }}</p>@endforeach
@if($parcel = $order->shipment)
<h2>Parcel tracking</h2><p>{{ $parcel->courier_name }} {{ $parcel->service_name }}</p>
@if($parcel->tracking_number)<p>Tracking number: {{ $parcel->tracking_number }}</p>@endif
@if($parcel->trackingUrl())<p><a href="{{ $parcel->trackingUrl() }}" target="_blank" rel="noopener noreferrer">Track parcel</a></p>@endif
<p>Parcel progress: {{ str_replace('_', ' ', $order->dispatch_status) }}.</p>
@endif
<h2>Delivery</h2><p>{{ $order->shipping_name }}</p><p>{{ $order->customer['name'] }}<br>{{ $order->customer['address'] }}<br>{{ $order->customer['city'] }}, {{ $order->customer['region'] }} {{ $order->customer['postal_code'] ?? '' }}<br>Pakistan</p>
<h2>Original purchase totals</h2>
@include('shop.order-promotion')
@foreach(['subtotal','discount','tax','shipping','total'] as $field)<p class="total-row"><span>{{ ucfirst($field) }}</span><strong>{{ $field === 'shipping' && $order->shipping_minor === 0 ? 'Free' : \App\Services\Pricing::format($order->{$field.'_minor'},$order->currency) }}</strong></p>@endforeach
<p>Collected: {{ \App\Services\Pricing::format($order->collected_minor,$order->currency) }}. Refunded: {{ \App\Services\Pricing::format((int)\Illuminate\Support\Facades\DB::table('order_refunds')->where('order_id',$order->id)->sum('amount_minor'),$order->currency) }}.</p>
</section><section><h2>Payment instructions</h2><p class="preserve-lines">{{ $order->payment_instructions }}</p>
@if($order->expires_at)<p>Unpaid transfer reservation expires: {{ $order->expires_at->timezone('Asia/Karachi')->format('d M Y H:i') }} PKT.</p>@endif
@if($order->payment_method==='bank' && $order->payment_status!=='paid' && in_array($order->status,['pending','confirmed']))
<form action="{{ route('orders.proof',$order->access_token) }}" method="post" enctype="multipart/form-data">@csrf<label>Private transfer proof (JPEG, PNG or PDF; up to 5 MB)<input type="file" name="proof" accept="image/jpeg,image/png,application/pdf" required></label><button class="button">Submit proof for review</button></form>@endif
<h2>Timeline</h2><ol>
@unless($order->events->contains('event_key', 'confirmation'))<li>{{ $order->created_at }}: Order received.</li>@endunless
@if($order->events->isEmpty())
@foreach($order->histories as $entry)<li>{{ $entry->created_at }}: Order {{ $entry->status }} / payment {{ str_replace('_',' ',$entry->payment_status) }}.</li>@endforeach
@endif
@foreach($order->events as $entry)<li>{{ $entry->created_at }}: {{ $entry->message }}</li>@endforeach
</ol>
@if($care::canCancel($order) && !$order->requests->where('type','cancellation')->where('status','pending')->count())
<details class="order-cancellation"><summary>Need to cancel your order?</summary><p>You can request cancellation before dispatch. We will review your request before shipping. Your order is cancelled only after approval; any payment refund is handled separately.</p>
<form action="{{ route('orders.request',$order->access_token) }}" method="post">@csrf<input type="hidden" name="type" value="cancellation"><input type="hidden" name="submission_key" value="{{ \Illuminate\Support\Str::uuid() }}"><label>Reason<textarea name="reason" required maxlength="1000"></textarea></label><button class="button">Request cancellation</button></form>
 </details>
@endif
@if($care::canReturn($order))
<h2>Request a return</h2><p>Select the quantities you want to return. Pending, approved and already received units are excluded. Contact the store before sending anything.</p>
<form action="{{ route('orders.request',$order->access_token) }}" method="post">@csrf<input type="hidden" name="type" value="return"><input type="hidden" name="submission_key" value="{{ \Illuminate\Support\Str::uuid() }}">
@foreach($order->lines as $line) @php($remaining=$care::remaining($order,$line->id))
<label>{{ $line->product_name }} / {{ $line->color }} / {{ $line->size }} (purchased {{ $line->quantity }}; eligible {{ $remaining }})<input type="number" name="quantities[{{ $line->id }}]" min="0" max="{{ $remaining }}" value="0" required></label>
@endforeach
<label>Reason<textarea name="reason" required maxlength="1000"></textarea></label><button class="button">Request selected returns</button></form>
@endif
<h2>Your requests</h2>
@forelse($order->requests as $r)<article><h3>{{ ucfirst($r->type) }} #{{ $r->id }}: {{ $r->status }}</h3><p>{{ $r->reason }}</p>
@foreach($r->lines as $item)<p>{{ $item->orderLine->product_name }} / {{ $item->orderLine->color }} / {{ $item->orderLine->size }}: {{ $item->quantity }}</p>@endforeach
@if($r->explanation)<p>Store response: {{ $r->explanation }}</p>@endif</article>
@empty<p>No requests submitted.</p>@endforelse
</section></div></main>
@endsection
