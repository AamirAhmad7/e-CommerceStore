@extends('layouts.store')
@section('title', 'International inquiry')
@section('content')
<main id="product" class="product-shell"><h1 class="page-heading">Your international inquiry</h1><p>Independently set USD prices. No payment or stock reservation is made here. The store confirms the final quote.</p>
<form action="{{ route('inquiry.update') }}" method="post" class="inquiry-form">@csrf
@forelse($quote['lines'] as $line)
<section class="inquiry-line"><h2>{{ $line['product']?->name ?? 'Unavailable jacket' }}</h2><p>{{ $line['variant']?->productColor->name }} / {{ $line['variant']?->size }} &middot; {{ $line['variant']?->sku }}</p><p>{{ \App\Services\Pricing::format($line['price'], 'USD') }} each</p>
<label>Quantity (0 removes this size)<input type="number" min="0" max="100" name="quantities[{{ $line['id'] }}]" value="{{ old('quantities.'.$line['id'], $line['quantity']) }}" required></label>
@if(!$line['ready'])<p role="alert">This exact size is no longer available in the requested quantity, or its USD price is missing. Available: {{ $line['available'] }}. Update or remove it before sending.</p>@endif</section>
@empty<p>Your inquiry is empty. Choose a jacket and size to begin.</p>@endforelse
<label>Delivery country<input name="destination" autocomplete="country-name" maxlength="80" placeholder="For example, United Kingdom" value="{{ old('destination', $destination) }}"></label><button class="button">Update inquiry &amp; shipping estimate</button>
</form>
<a class="text-button" href="{{ route('shop', ['market'=>'international']) }}">Add another jacket</a>
@if(count($quote['lines']))<section class="order-review"><h2>Review your inquiry</h2><p>Merchandise: {{ \App\Services\Pricing::format($quote['subtotal'], 'USD') }}</p><p>{{ $shipping['wording'] }}</p>
@if($quote['valid'] && $shipping['total'] !== null)<p>Shipping: {{ \App\Services\Pricing::format($shipping['shipping'], 'USD') }}</p><p><strong>Estimated total: {{ \App\Services\Pricing::format($shipping['total'], 'USD') }}</strong></p>@endif
@if($whatsappUrl)<a class="whatsapp-button" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer">@include('shop.whatsapp-icon') Send inquiry on WhatsApp</a>@elseif($destination === '')<p>Enter your delivery country and update the inquiry to prepare your message.</p>@elseif($quote['valid'])<p>Contact the store with this inquiry:</p><textarea readonly rows="8" aria-label="Inquiry message">{{ $message }}</textarea><a href="{{ route('information.contact') }}">Contact us</a>@endif
</section>@endif
<p><a href="{{ route('policies') }}">Delivery &amp; returns</a></p></main>
@endsection
