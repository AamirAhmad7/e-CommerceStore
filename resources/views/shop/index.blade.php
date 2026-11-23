@extends('layouts.store')
@section('meta')
<meta property="og:type" content="website"><meta property="og:title" content="{{ $seoTitle }}"><meta property="og:description" content="{{ $metaDescription }}"><meta property="og:url" content="{{ $canonical }}">
@endsection
@section('content')
<main id="product" class="product-shell catalog-page">
@if($home)<section class="shop-intro"><p class="eyebrow">{{ $brand }}</p><h1>Find your next jacket.</h1><p>Explore the collection by color, size and category.</p><a class="button" href="{{ route('shop') }}">Browse all products</a></section>@else<h1 class="page-heading">{{ $title }}</h1>@endif

<div class="catalog-layout">
<aside class="catalog-filters" aria-label="Product filters">@include('shop.listing-toolbar')@include('shop.filters')</aside>
<section class="catalog-results" aria-label="Product results">@include('shop.results-summary')
<div class="product-cards">
@forelse($cards as $card)
@include('products.card',['priority'=>$loop->first, 'listing' => true])
@empty @include('shop.empty') @endforelse
</div><div class="pagination">{{ $products->links() }}</div>
</section></div>
</main>
@endsection
