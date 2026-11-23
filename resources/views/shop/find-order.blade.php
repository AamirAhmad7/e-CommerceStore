@extends('layouts.store')
@section('title', 'Find my order | '.\App\Support\StoreSettings::brandName())
@section('content')
<main id="product" class="product-shell recovery-page">
    <h1 class="page-heading">Find my order</h1>
    <p>Enter your order reference and checkout phone number or email. Matching phone details open your order; matching email details receive a private link.</p>
    <form action="{{ route('orders.lookup') }}" method="post" class="checkout-fields">
        @csrf
        <label for="find-reference">Order reference</label><input id="find-reference" name="number" required maxlength="100" autocomplete="off" autocapitalize="characters" spellcheck="false" value="{{ old('number') }}" aria-invalid="{{ $errors->has('number')?'true':'false' }}">@error('number')<p class="field-error">{{ $message }}</p>@enderror
        <label for="find-contact">Checkout phone or email</label><input id="find-contact" name="contact" required maxlength="254" autocomplete="off" value="{{ old('contact') }}">@error('contact')<p class="field-error">{{ $message }}</p>@enderror
        <button class="button">Find my order</button>
    </form>
</main>
@endsection
