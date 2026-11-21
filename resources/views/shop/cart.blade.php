@extends('layouts.store')
@section('title','Your cart | '.\App\Support\StoreSettings::brandName())
@section('content')
<main id="product" class="product-shell"><h1 class="page-heading">Your cart</h1><a class="text-button continue-shopping" href="{{ session('continue_shopping',route('shop')) }}">Continue shopping</a>
@if($changed)<p class="notice">A price or stock level changed since your last review. Check the updated amounts and quantities before continuing.</p>@endif
@if(!$quote['lines'])<p>Your cart is empty.</p><a class="button" href="{{ route('shop') }}">Browse products</a>@else
@foreach($quote['lines'] as $line)
<article class="cart-line">
@php($media=$line['variant']?->productColor?->getFirstMedia('gallery'))
@if($media)@php($photo=\App\Support\ProductPhoto::data($media))<img src="{{ $photo['thumbnail'] ?? $photo['src'] }}" width="96" height="120" alt="{{ $photo['alt'] }}" loading="lazy">@endif
<div><h2>{{ $line['product']?->name ?? 'Unavailable product' }}</h2><p>{{ $line['variant']?->productColor?->name }} / {{ $line['variant']?->size }} / {{ $line['variant']?->sku }}</p><p>{{ \App\Services\Pricing::format($line['price'],$quote['currency']) }} each</p>
@if(!$line['valid'])<p role="alert">This item cannot be ordered at this quantity. Available: {{ $line['available'] }}. Remove it or reduce the quantity; missing prices require store configuration.</p>@endif</div>
<form action="{{ route('cart.update',$line['id']) }}" method="post">@csrf @method('PATCH')<label>Quantity<input type="number" name="quantity" min="0" max="100" value="{{ $line['quantity'] }}" required aria-label="Quantity for {{ $line['variant']?->sku }}"></label><button class="button">Update</button><button name="quantity" value="0" class="text-button">Remove</button></form>
<strong>{{ \App\Services\Pricing::format($line['total'],$quote['currency']) }}</strong>
@if(isset($promotion['lines'][$line['id']]) && $promotion['lines'][$line['id']]['discount']>0)<p>You save {{ \App\Services\Pricing::format($promotion['lines'][$line['id']]['discount']) }} on this line.</p>@endif
</article>@endforeach
@if($quote['currency']==='PKR')
<section class="promotion-controls" aria-label="Discount code"><form action="{{ route('cart.promotion') }}" method="post">@csrf<label for="promotion-code">Discount code</label><input id="promotion-code" name="code" value="{{ old('code',session('promotion_code')) }}" maxlength="40" autocapitalize="characters" autocomplete="off" aria-describedby="code-error"><button class="button">Apply</button>@error('code')<p id="code-error" role="alert">{{ $message }}</p>@enderror</form>
@if(session('promotion_code'))<form action="{{ route('cart.promotion.remove') }}" method="post">@csrf @method('DELETE')<button class="text-button">Remove {{ session('promotion_code') }}</button></form>@endif
@include('shop.promotion-summary')
</section>
@else<p>PKR offers are checked at Pakistan checkout. No exchange rate is applied. <a href="{{ route('shop',['currency'=>'PKR']) }}">Switch to PKR shopping to enter a code.</a></p>@endif
<section class="cart-summary"><h2>Your order summary</h2><p class="total-row"><span>Merchandise subtotal</span><strong>{{ \App\Services\Pricing::format($quote['subtotal'],$quote['currency']) }}</strong></p>
@if($promotion)
@foreach(['discount'=>'Promotion discount','shipping'=>'Standard shipping','tax'=>'Tax','total'=>'Estimated PKR payable'] as $field=>$label)<p class="total-row {{ $field==='total'?'grand-total':'' }}"><span>{{ $label }}</span><strong>{{ $field==='discount'?'- ':'' }}{{ \App\Services\Pricing::format($promotion['totals'][$field.'_minor']) }}</strong></p>@endforeach
@else<p>Delivery, tax and any PKR promotion are confirmed at checkout.</p>@endif
<p>Pakistan checkout uses PKR selling prices. Review the final amount before confirming.</p><a class="button" href="{{ route('checkout.show') }}">Review Pakistan checkout in PKR</a></section>
@endif
<p class="service-link">Delivery charges are confirmed at checkout. <a href="{{ route('policies') }}">Delivery &amp; returns information</a></p>
</main>
@endsection
