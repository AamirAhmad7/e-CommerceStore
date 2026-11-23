@extends('layouts.store')
@section('title', $seoTitle)
@section('meta')
@if($preview)<meta name="robots" content="noindex, nofollow, noarchive">@endif
<meta property="og:type" content="website"><meta property="og:title" content="{{ $seoTitle }}"><meta property="og:description" content="{{ $metaDescription }}"><meta property="og:url" content="{{ $canonical }}">
@if($hero)<meta property="og:image" content="{{ $hero['src'] }}"><meta property="og:image:alt" content="{{ $hero['alt'] }}"><meta property="og:image:width" content="{{ $hero['width'] }}"><meta property="og:image:height" content="{{ $hero['height'] }}">@endif
@endsection
@section('content')
@if($preview)<aside class="preview-banner">Homepage draft preview. Save draft edits before previewing. Customers still see the published version.</aside>@endif
<main id="product" class="product-shell home-page">
    <section class="home-hero {{ $hero?'has-image':'' }}">
        <div class="hero-copy"><p class="eyebrow">{{ $brand }}</p><h1 class="page-heading">{{ $headline }}</h1>
            @if(filled($content['supporting_text']??null))<p class="campaign-intro">{{ $content['supporting_text'] }}</p>@else<p class="campaign-intro">Find your next jacket. Explore colors, compare sizes, and choose your fit.</p>@endif
            <a class="button" href="{{ $primaryUrl }}">{{ $primaryLabel }} <span aria-hidden="true">&rarr;</span></a><a class="hero-secondary" href="#home-products">Explore the jackets &darr;</a>
        </div>
        @if($hero)<img src="{{ $hero['src'] }}" @if($hero['srcset']) srcset="{{ $hero['srcset'] }}" @endif sizes="(min-width:1440px) 650px, (min-width:960px) 48vw, 92vw" width="{{ $hero['width'] }}" height="{{ $hero['height'] }}" alt="{{ $hero['alt'] }}" fetchpriority="high" loading="eager">@endif
    </section>
    <nav class="home-shortcuts" aria-label="Explore the shop"><span>Find your style</span><a href="{{ route('shop') }}">Browse all products <span aria-hidden="true">&nearr;</span></a>
        @foreach(\App\Support\StoreNavigation::links() as $link)<a href="{{ $link['url'] }}">{{ $link['label'] }} <span aria-hidden="true">&nearr;</span></a>@endforeach
        <a href="{{ route('shop', ['sort' => 'newest']) }}">Latest arrivals <span aria-hidden="true">&nearr;</span></a>
    </nav>
    <div id="home-products" class="home-products">
    @foreach($sections as $section)
        <section class="featured-collection"><div class="collection-heading"><div class="collection-heading-title">@if($section['cover'])<a class="collection-cover" href="{{ $section['url'] }}" tabindex="-1" aria-hidden="true"><img src="{{ $section['cover']['thumbnail'] ?? $section['cover']['src'] }}" width="96" height="112" alt="" loading="lazy" decoding="async"></a>@endif<h2><a href="{{ $section['url'] }}">{{ $section['collection']->title }}</a></h2></div><a class="text-button" href="{{ $section['url'] }}">Shop {{ $section['collection']->title }}</a></div>
        <div class="product-cards">@foreach($section['cards'] as $card) @include('products.card',['priority'=>!$hero && $loop->parent->first && $loop->first]) @endforeach</div></section>
    @endforeach
    @if($sections->isEmpty() && $fallbackCards->isNotEmpty())
        <section class="featured-collection"><div class="collection-heading"><h2>Explore our products</h2><a href="{{ route('shop') }}">View all products</a></div><div class="product-cards">@foreach($fallbackCards as $card) @include('products.card',['priority'=>!$hero && $loop->first]) @endforeach</div></section>
    @endif
    </div>
    <section class="home-help" aria-labelledby="home-help-title">
        <div><p class="eyebrow">A little help choosing</p><h2 id="home-help-title">Find the right jacket for you.</h2><p>Check available sizes on each jacket, or get in touch before you order.</p><a class="button" href="{{ route('information.contact') }}">Talk to us <span aria-hidden="true">&rarr;</span></a></div>
        <nav aria-label="Shopping help"><a href="{{ route('policies') }}"><strong>Delivery &amp; returns</strong><span>Read our policy before ordering &rarr;</span></a><a href="{{ route('orders.find') }}"><strong>Already ordered?</strong><span>Find your order &rarr;</span></a><a href="{{ route('information.about') }}"><strong>Meet Sialkot Fashion Studio</strong><span>About our store &rarr;</span></a></nav>
    </section>
</main>
@endsection
