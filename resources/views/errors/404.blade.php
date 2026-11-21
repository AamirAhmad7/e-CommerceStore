@extends('layouts.store')
@section('title','Page not found | '.\App\Support\StoreSettings::brandName())
@section('meta')<meta name="robots" content="noindex, follow">@endsection
@section('content')<main id="product" class="product-shell"><p class="eyebrow">Page not found</p><h1 class="page-heading">Let us help you find your next jacket.</h1><p>This page may have moved or is no longer available.</p><form method="get" action="{{ route('shop') }}" class="checkout-fields"><label for="missing-search">Search the shop</label><input type="search" id="missing-search" name="q" placeholder="Product name, color or SKU" maxlength="160"><button class="button">Search products</button></form><p><a class="text-button" href="{{ route('shop') }}">Browse all products</a></p></main>@endsection
