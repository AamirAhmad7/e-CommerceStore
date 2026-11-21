@extends('layouts.store')
@section('title', 'Find my order | '.\App\Support\StoreSettings::brandName())
@section('content')
<main id="product" class="product-shell recovery-page">
    <h1 class="page-heading">Find my order</h1>
    <p>Enter the reference and email address used at checkout. We will send a private access link if they match.</p>
    <form action="{{ route('orders.lookup') }}" method="post" class="checkout-fields">
        @csrf
        <label for="find-reference">Order reference</label><input id="find-reference" name="number" required maxlength="100" autocomplete="off" autocapitalize="characters" spellcheck="false" value="{{ old('number') }}" aria-invalid="{{ $errors->has('number')?'true':'false' }}">@error('number')<p class="field-error">{{ $message }}</p>@enderror
        <label for="find-email">Checkout email</label><input id="find-email" name="email" type="email" required maxlength="254" autocomplete="email" value="{{ old('email') }}" aria-invalid="{{ $errors->has('email')?'true':'false' }}">@error('email')<p class="field-error">{{ $message }}</p>@enderror
        <button class="button">Email my order link</button>
    </form>
</main>
@endsection
