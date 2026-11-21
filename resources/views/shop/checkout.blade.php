@extends('layouts.store')
@section('title','Pakistan checkout | '.\App\Support\StoreSettings::brandName())
@section('content')
<main id="product" class="product-shell"><h1 class="page-heading">Pakistan checkout</h1><p>Review your PKR selling prices and delivery details. USD browsing prices are not converted.</p>
@if($error)<div class="notice error" role="alert">{{ $error }}</div><a class="button-secondary" href="{{ isset($assistedLink) ? $assistedLink->url() : route('cart.show') }}">{{ isset($assistedLink) ? 'Return to your selection' : 'Return to cart' }}</a>@else
<form action="{{ route('checkout.store') }}" method="post" class="checkout-grid">@csrf<input type="hidden" name="checkout_key" value="{{ $key }}"><input type="hidden" name="country" value="PK">
<div><fieldset><legend>1. Contact</legend>
@foreach(['name'=>['Full name','text','name',120],'email'=>['Email','email','email',254],'phone'=>['Phone','tel','tel',25]] as $field=>[$label,$type,$autocomplete,$length])
<label for="checkout-{{ $field }}">{{ $label }}</label><input id="checkout-{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field) }}" required maxlength="{{ $length }}" autocomplete="{{ $autocomplete }}" aria-invalid="{{ $errors->has($field)?'true':'false' }}" @if($errors->has($field)) aria-describedby="error-{{ $field }}" @endif>
@error($field)<p class="field-error" id="error-{{ $field }}">{{ $message }}</p>@enderror
@endforeach</fieldset>
<fieldset><legend>2. Delivery address</legend><p>Pakistan only.</p>
@foreach(['address'=>['Street address','street-address',500],'city'=>['City','address-level2',100],'region'=>['Province / region','address-level1',100],'postal_code'=>['Postal code (optional)','postal-code',20]] as $field=>[$label,$autocomplete,$length])
<label for="checkout-{{ $field }}">{{ $label }}</label><input id="checkout-{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" @required($field!=='postal_code') maxlength="{{ $length }}" autocomplete="{{ $autocomplete }}" aria-invalid="{{ $errors->has($field)?'true':'false' }}" @if($errors->has($field)) aria-describedby="error-{{ $field }}" @endif>
@error($field)<p class="field-error" id="error-{{ $field }}">{{ $message }}</p>@enderror
@endforeach</fieldset>
<fieldset><legend>3. Payment</legend>@foreach($methods as $method)<label class="radio-label"><input type="radio" name="payment_method" value="{{ $method->code() }}" data-payment-label="{{ $method->code()==='cod'?'Cash on delivery':'Manual bank transfer' }}" @checked(old('payment_method')===$method->code()) required>{{ $method->code()==='cod' ? 'Cash on delivery' : 'Manual bank transfer' }}</label>@endforeach
@error('payment_method')<p class="field-error">{{ $message }}</p>@enderror
<p>Bank instructions and private proof upload appear after ordering. Uploading proof does not mark an order paid.</p></fieldset></div>
<section class="order-review"><h2>4. Review your PKR order</h2>@foreach($quote['lines'] as $line)<p>{{ $line['product']->name }} / {{ $line['variant']->productColor->name }} / {{ $line['variant']->size }} x {{ $line['quantity'] }}<br>{{ \App\Services\Pricing::format($line['total']) }}</p>@endforeach
@include('shop.promotion-summary')
<p>Delivery: {{ $totals['shipping_name'] }}</p>
@foreach(['subtotal'=>'Merchandise subtotal','discount'=>'Promotion discount','shipping'=>'Shipping','tax'=>'Tax','total'=>'Final PKR payable'] as $field=>$label)<p class="total-row {{ $field==='total'?'grand-total':'' }}"><span>{{ $label }}</span><strong>{{ $field==='discount'?'- ':'' }}{{ \App\Services\Pricing::format($totals[$field.'_minor']) }}</strong></p>@endforeach
<p data-payment-summary>Payment: {{ old('payment_method')==='cod'?'Cash on delivery':(old('payment_method')==='bank'?'Manual bank transfer':'Choose a payment method above.') }}</p>
@if(isset($assistedLink) && $assistedLink->subtotal_minor !== $quote['subtotal'])
<div class="notice"><p>Merchandise prices changed since this selection was shared. Previous subtotal: {{ \App\Services\Pricing::format($assistedLink->subtotal_minor) }}. Current subtotal: {{ \App\Services\Pricing::format($quote['subtotal']) }}.</p><label class="radio-label"><input type="checkbox" name="accept_revised_total" value="1" required>I accept the revised prices and current PKR total.</label>@error('accept_revised_total')<p class="field-error">{{ $message }}</p>@enderror</div>
@endif
<label class="radio-label"><input type="checkbox" name="reviewed" value="1" required>I have reviewed this PKR total and my delivery details.</label>@error('reviewed')<p class="field-error">{{ $message }}</p>@enderror
<button class="button" type="submit">Place order / {{ \App\Services\Pricing::format($totals['total_minor']) }}</button></section>
</form>@endif</main>
@endsection