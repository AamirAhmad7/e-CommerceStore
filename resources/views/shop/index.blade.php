@extends('layouts.store')
@section('meta')
<meta property="og:type" content="website"><meta property="og:title" content="{{ $seoTitle }}"><meta property="og:description" content="{{ $metaDescription }}"><meta property="og:url" content="{{ $canonical }}">
@endsection
@section('content')
<main id="product" class="product-shell">
@if($home)<section class="shop-intro"><p class="eyebrow">{{ $brand }}</p><h1>Find your next jacket.</h1><p>Explore the collection by color, size and category.</p><a class="button" href="{{ route('shop') }}">Browse all products</a></section>@else<h1 class="page-heading">{{ $title }}</h1>@endif
<nav class="category-nav" aria-label="Categories">@foreach($categories as $item)<a href="{{ route('categories.show',$item->slug) }}">{{ $item->name }}</a>@endforeach</nav>
@include('shop.filters')
<div class="product-cards">
@forelse($cards as $card)
@include('products.card',['priority'=>$loop->first])
@empty @include('shop.empty') @endforelse
</div><div class="pagination">{{ $products->links() }}</div>
</main>
@endsection
