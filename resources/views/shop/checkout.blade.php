@extends('layouts.store')
@section('title','Checkout | '.\App\Support\StoreSettings::brandName())
@section('content')
@php($selectedPayment = old('payment_method', session('checkout_input.payment_method', count($methods) === 1 ? reset($methods)->code() : null)))
<main id="product" class="product-shell"><h1 class="page-heading">Checkout</h1><p>Enter your delivery address, choose an available payment method, and review the final total.</p>
@if($error)
<div class="notice error" role="alert">{{ $error }} @unless($setupBlocked)Your saved contact and address details are kept while you update the cart.@endunless</div>
@if($setupBlocked)
<a class="button-secondary" href="{{ route('information.contact') }}">Contact the store</a>

@else<a class="button-secondary" href="{{ isset($assistedLink) ? $assistedLink->url() : route('cart.show') }}">{{ isset($assistedLink) ? 'Return to your selection' : 'Return to cart' }}</a>@endif
@endif
@unless($setupBlocked)
<form action="{{ route('checkout.store') }}" method="post" class="checkout-grid">@csrf<input type="hidden" name="checkout_key" value="{{ $key }}"><input type="hidden" name="country" value="PK">
<div><fieldset><legend>1. Contact</legend>
@foreach(['name'=>['Full name','text','name',120],'email'=>['Email (optional)','email','email',254],'phone'=>['Phone','tel','tel',25]] as $field=>[$label,$type,$autocomplete,$length])
<label for="checkout-{{ $field }}">{{ $label }}</label><input id="checkout-{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field, session('checkout_input.'.$field)) }}" @required($field !== 'email') @if($field === 'phone') inputmode="tel" pattern="(?:03[0-9]{2}(?: |-)?[0-9]{7}|\+?923[0-9]{9})" aria-describedby="phone-hint" @endif maxlength="{{ $length }}" autocomplete="{{ $autocomplete }}" aria-invalid="{{ $errors->has($field)?'true':'false' }}" @if($errors->has($field)) aria-describedby="error-{{ $field }}" @endif>
@if($field === 'phone')<small id="phone-hint">03XX XXXXXXX</small>@endif
@error($field)<p class="field-error" id="error-{{ $field }}">{{ $message }}</p>@enderror
@endforeach</fieldset>
<fieldset><legend>2. Delivery address</legend>
<label for="checkout-address">Complete street address</label>
<textarea id="checkout-address" name="address" rows="3" required maxlength="500" autocomplete="street-address" placeholder="House / flat, street, area and nearby landmark" aria-describedby="address-help error-address">{{ old('address', session('checkout_input.address')) }}</textarea>
<small id="address-help">Enter your address here, then the city / district and province below.</small>
<p class="field-error" id="error-address">@error('address'){{ $message }}@enderror</p>
<div class="address-locality">@foreach(['city'=>['City / district','address-level2',100],'region'=>['Province / territory','address-level1',100],'postal_code'=>['Postal code (optional)','postal-code',20]] as $field=>[$label,$autocomplete,$length])
<div class="address-field"><label for="checkout-{{ $field }}">{{ $label }}</label><input id="checkout-{{ $field }}" @if(in_array($field, ['city', 'region'])) list="checkout-{{ $field }}-options" @endif name="{{ $field }}" value="{{ old($field, session('checkout_input.'.$field)) }}" @required($field!=='postal_code') maxlength="{{ $length }}" autocomplete="{{ $autocomplete }}" aria-invalid="{{ $errors->has($field)?'true':'false' }}" @if($errors->has($field)) aria-describedby="error-{{ $field }}" @endif>
@error($field)<p class="field-error" id="error-{{ $field }}">{{ $message }}</p>@enderror
</div>@endforeach</div><label for="order-notes">Order notes (optional)</label><textarea id="order-notes" name="order_notes" maxlength="1000" rows="3" placeholder="Delivery directions or anything we should know">{{ old('order_notes', session('checkout_input.order_notes')) }}</textarea>@error('order_notes')<p class="field-error">{{ $message }}</p>@enderror</fieldset>
<datalist id="checkout-region-options">@foreach(['Punjab', 'Sindh', 'Khyber Pakhtunkhwa', 'Balochistan', 'Islamabad Capital Territory', 'Azad Jammu and Kashmir', 'Gilgit-Baltistan'] as $province)<option value="{{ $province }}">@endforeach</datalist><datalist id="checkout-city-options">@foreach(\App\Support\PakistanAddress::cities() as $city=>$province)<option value="{{ $city }}" data-province="{{ $province }}">@endforeach</datalist><fieldset><legend>3. Payment</legend>@foreach($methods as $method)<label class="radio-label"><input type="radio" name="payment_method" value="{{ $method->code() }}" data-payment-label="{{ $method->code()==='cod'?'Cash on delivery':'Manual bank transfer' }}" @checked( $selectedPayment===$method->code()) required>{{ $method->code()==='cod' ? 'Cash on delivery' : 'Manual bank transfer' }}</label>@endforeach
@error('payment_method')<p class="field-error">{{ $message }}</p>@enderror
@if(!$methods)<p role="status">Payment methods are not available yet. Please <a href="{{ route('information.contact') }}">contact the store</a> before ordering.</p>@else<p>{{ count($methods) === 1 && reset($methods)->code() === 'cod' ? 'Pay in cash when your order is delivered.' : 'Choose how you would like to pay. Bank transfer instructions appear after ordering.' }}</p>@endif</fieldset></div>
<section class="order-review"><h2>Order summary</h2><p class="trust-line">Cash on delivery &middot; <a href="{{ route('information.contact') }}">Need help?</a></p><a href="{{ route('cart.show') }}">Edit cart</a>@foreach($quote['lines'] as $line)@php($media = $line['variant']?->productColor?->getFirstMedia('gallery'))<div class="checkout-item">@if($media)@php($photo = \App\Support\ProductPhoto::data($media))<img src="{{ $photo['thumbnail'] ?? $photo['src'] }}" alt="{{ $line['product']?->name }}" width="64" height="80">@endif<p>{{ $line['product']?->name ?? 'Unavailable product' }} / {{ $line['variant']?->productColor?->name }} / {{ $line['variant']?->size }} x {{ $line['quantity'] }}<br>{{ \App\Services\Pricing::format($line['total']) }}</p></div>@endforeach
@if($totals && !$error)
@include('shop.promotion-summary')
<p>Delivery: {{ $totals['shipping_name'] }}</p>
@foreach(['subtotal'=>'Merchandise subtotal','discount'=>'Promotion discount','shipping'=>'Shipping','total'=>'Total to pay'] as $field=>$label)<p class="total-row {{ $field==='total'?'grand-total':'' }}"><span>{{ $label }}</span><strong>{{ $field==='discount'?'- ':'' }}{{ $field === 'shipping' && $totals['shipping_minor'] === 0 ? 'Free' : \App\Services\Pricing::format($totals[$field.'_minor']) }}</strong></p>@endforeach
<p data-payment-summary>Payment: {{ $selectedPayment==='cod'?'Cash on delivery':($selectedPayment==='bank'?'Manual bank transfer':'Choose a payment method above.') }}</p>
@if(isset($assistedLink) && $assistedLink->subtotal_minor !== $quote['subtotal'])
<div class="notice"><p>Merchandise prices changed since this selection was shared. Previous subtotal: {{ \App\Services\Pricing::format($assistedLink->subtotal_minor) }}. Current subtotal: {{ \App\Services\Pricing::format($quote['subtotal']) }}.</p><label class="radio-label"><input type="checkbox" name="accept_revised_total" value="1" required>I accept the revised prices and current PKR total.</label>@error('accept_revised_total')<p class="field-error">{{ $message }}</p>@enderror</div>
@endif
<label class="radio-label"><input type="checkbox" name="reviewed" value="1" required>I agree to the <a href="{{ route('policies') }}" target="_blank" rel="noopener">Delivery &amp; Returns policy</a>.</label>@error('reviewed')<p class="field-error">{{ $message }}</p>@enderror
<button class="button" type="submit">Place order / {{ \App\Services\Pricing::format($totals['total_minor']) }}</button>
@else<p>{{ $setupBlocked ? 'The store needs to confirm its delivery charges before an order can be placed.' : 'Your final total cannot be confirmed yet. Review the message above before placing an order.' }}</p><button class="button" type="button" disabled>Ordering currently unavailable</button>@endif</section>
@if(config('store.confirm_cod_whatsapp'))<p class="cod-trust">We'll confirm your order on WhatsApp before dispatch.</p>@endif</form>@endunless<p class="service-link"><a href="{{ route('policies') }}">Delivery &amp; returns</a></p></main>
@endsection
