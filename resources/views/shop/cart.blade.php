@extends('layouts.store')
@section('title','Your cart | '.\App\Support\StoreSettings::brandName())
@section('content')
<main id="product" class="product-shell"><h1 class="page-heading">Your cart</h1><a class="text-button continue-shopping" href="{{ session('continue_shopping',route('shop')) }}">Continue shopping</a>
@if($changed)<p class="notice">A price or stock level changed since your last review. Check the updated amounts and quantities before continuing.</p>@endif
@if(!$quote['lines'])<p>Your next favourite jacket is waiting. Your cart is empty for now.</p><a class="button" href="{{ route('shop') }}">Continue shopping</a>@else
@foreach($quote['lines'] as $line)
<article class="cart-line">
@php($media=$line['variant']?->productColor?->getFirstMedia('gallery'))
@if($media)@php($photo=\App\Support\ProductPhoto::data($media))<img src="{{ $photo['thumbnail'] ?? $photo['src'] }}" width="96" height="120" alt="{{ $photo['alt'] }}" loading="lazy">@endif
<div><h2>{{ $line['product']?->name ?? 'Unavailable product' }}</h2><p>{{ $line['variant']?->productColor?->name }} / {{ $line['variant']?->size }} / {{ $line['variant']?->sku }}</p><p>{{ \App\Services\Pricing::format($line['price'],$quote['currency']) }} each</p>
@if($line['available'] === 1)<p class="stock-note">Last piece</p>@endif
@if(!$line['valid'])<p role="alert">This item cannot be ordered at this quantity. Available: {{ $line['available'] }}. Remove it or reduce the quantity; contact us if you need help.</p>@endif</div>
<form action="{{ route('cart.update',$line['id']) }}" method="post" data-cart-quantity-form><input type="hidden" name="after_update" value="cart">@csrf @method('PATCH')<label>Quantity<input type="number" name="quantity" min="0" max="{{ min(100, $line['available']) }}" value="{{ $line['quantity'] }}" required aria-label="Quantity for {{ $line['variant']?->sku }}"></label><button class="button" data-update-quantity>Update quantity</button><button name="quantity" value="0" class="text-button">Remove</button></form>
<strong>{{ \App\Services\Pricing::format($line['total'],$quote['currency']) }}</strong>
@if(isset($promotion['lines'][$line['id']]) && $promotion['lines'][$line['id']]['discount']>0)<p>You save {{ \App\Services\Pricing::format($promotion['lines'][$line['id']]['discount']) }} on this line.</p>@endif
</article>@endforeach
@if($quote['currency']==='PKR')
<section class="promotion-controls" aria-label="Discount code"><form action="{{ route('cart.promotion') }}" method="post">@csrf<label for="promotion-code">Discount code</label><input id="promotion-code" name="code" value="{{ old('code',session('promotion_code')) }}" maxlength="40" autocapitalize="characters" autocomplete="off" aria-describedby="code-error"><button class="button">Apply</button>@error('code')<p id="code-error" role="alert">{{ $message }}</p>@enderror</form>
@if(session('promotion_code'))<form action="{{ route('cart.promotion.remove') }}" method="post">@csrf @method('DELETE')<button class="text-button">Remove {{ session('promotion_code') }}</button></form>@endif
@include('shop.promotion-summary')
</section>
@else<p>PKR offers are checked at Pakistan checkout. No exchange rate is applied. <a href="{{ route('shop',['currency'=>'PKR']) }}">Switch to PKR shopping to enter a code.</a></p>@endif
@php($shippingSettings = \App\Services\CommerceSettings::get())
@if($shippingSettings['free_shipping_minor'] > 0 && $promotion)
@php($remaining = $promotion['totals']['shipping_minor'] === 0 ? 0 : max(0, $shippingSettings['free_shipping_minor'] - ($quote['subtotal'] - $promotion['totals']['discount_minor'])))
<aside class="delivery-progress" aria-label="Free delivery progress"><p>{{ $remaining > 0 ? 'Add '.\App\Services\Pricing::format($remaining).' more for free delivery' : 'Your order qualifies for free delivery' }}</p><progress max="{{ $shippingSettings['free_shipping_minor'] }}" value="{{ $shippingSettings['free_shipping_minor'] - $remaining }}">Free delivery progress</progress></aside>
@endif
<section class="cart-summary"><h2>Your order summary</h2><p class="total-row"><span>Merchandise subtotal</span><strong>{{ \App\Services\Pricing::format($quote['subtotal'],$quote['currency']) }}</strong></p>
@if($promotion)
@foreach(['discount'=>'Promotion discount','shipping'=>'Standard shipping','total'=>'Estimated PKR payable'] as $field=>$label)<p class="total-row {{ $field==='total'?'grand-total':'' }}"><span>{{ $label }}</span><strong>{{ $field==='discount'?'- ':'' }}{{ $field === 'shipping' && $promotion['totals']['shipping_minor'] === 0 ? 'Free delivery' : \App\Services\Pricing::format($promotion['totals'][$field.'_minor']) }}</strong></p>@endforeach
@endif
<p>Shipping and final total shown before placing your order.</p>@if(\App\Services\CommerceSettings::quoteReady($quote))<a class="button" data-proceed-checkout href="{{ route('checkout.show') }}">Proceed to checkout</a><p>Enter your delivery address and choose your payment method next.</p>@else<p>Contact us to arrange your order.</p><a class="button" href="{{ route('information.contact') }}">Contact the store</a>@endif</section>
@endif
<p class="service-link"><a href="{{ route('policies') }}">Delivery &amp; returns information</a></p>
</main>
@endsection

