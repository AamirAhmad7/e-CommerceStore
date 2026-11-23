@extends('layouts.store')
@section('title', $seoTitle)
@section('meta')
@if($preview)<meta name="robots" content="noindex, nofollow, noarchive">@endif
<meta property="og:type" content="website"><meta property="og:title" content="{{ $seoTitle }}"><meta property="og:description" content="{{ $metaDescription }}"><meta property="og:url" content="{{ $canonical }}">
@if($cover)<meta property="og:image" content="{{ $cover['src'] }}"><meta property="og:image:alt" content="{{ $cover['alt'] }}"><meta property="og:image:width" content="{{ $cover['width'] }}"><meta property="og:image:height" content="{{ $cover['height'] }}">@endif
@endsection
@section('content')
@if($preview)<aside class="preview-banner">Staff preview. Saved changes only; only storefront-eligible products are shown. This does not publish the collection.</aside>@endif
<main id="product" class="product-shell catalog-page">
    <p class="breadcrumb"><a href="{{ route('shop') }}">Shop</a> / {{ $collection->title }}</p>
    <h1 class="page-heading">{{ $collection->title }}</h1>
    @if($collection->introduction)<p class="campaign-intro">{{ $collection->introduction }}</p>@endif
    @if(!$preview && $collection->is_published)
        <div class="share-controls" data-share-url="{{ $canonical }}" data-share-title="{{ $collection->title }}">
            <button type="button" class="button" data-share hidden>Share collection</button>
            <button type="button" class="text-button" data-copy-link hidden>Copy link</button>
            <label>Public collection link<input readonly value="{{ $canonical }}" aria-label="Public collection link" data-share-input></label>
            <span role="status" data-share-status></span>
        </div>
    @endif
    <div class="catalog-layout">
<aside class="catalog-filters" aria-label="Product filters">@include('shop.listing-toolbar')@include('shop.filters')</aside>
<section class="catalog-results" aria-label="Product results">@include('shop.results-summary')
    <div class="product-cards">@forelse($cards as $card) @include('products.card',['priority'=>$loop->first, 'listing' => true]) @empty @include('shop.empty') @endforelse</div>
    <div class="pagination">{{ $items->links() }}</div>
</section></div>
</main>
@endsection
