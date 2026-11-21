@extends('layouts.store')
@section('title', $seoTitle)
@section('meta')
@if($preview)<meta name="robots" content="noindex, nofollow, noarchive">@endif
<meta property="og:type" content="website"><meta property="og:title" content="{{ $seoTitle }}"><meta property="og:description" content="{{ $metaDescription }}"><meta property="og:url" content="{{ $canonical }}">
@if($hero)<meta property="og:image" content="{{ $hero['src'] }}"><meta property="og:image:alt" content="{{ $hero['alt'] }}"><meta property="og:image:width" content="{{ $hero['width'] }}"><meta property="og:image:height" content="{{ $hero['height'] }}">@endif
@endsection
@section('content')
@if($preview)<aside class="preview-banner">Homepage draft preview. Save draft edits before previewing. Customers still see the published version.</aside>@endif
<main id="product" class="product-shell">
    <section class="home-hero {{ $hero?'has-image':'' }}">
        <div class="hero-copy"><p class="eyebrow">{{ $brand }}</p><h1 class="page-heading">{{ $headline }}</h1>
            @if(filled($content['supporting_text']??null))<p class="campaign-intro">{{ $content['supporting_text'] }}</p>@endif
            <a class="button" href="{{ $primaryUrl }}">{{ $primaryLabel }}</a>
        </div>
        @if($hero)<img src="{{ $hero['src'] }}" @if($hero['srcset']) srcset="{{ $hero['srcset'] }}" @endif sizes="(min-width:1440px) 650px, (min-width:960px) 48vw, 92vw" width="{{ $hero['width'] }}" height="{{ $hero['height'] }}" alt="{{ $hero['alt'] }}" fetchpriority="high" loading="eager">@endif
    </section>
    @foreach($sections as $section)
        <section class="featured-collection"><div class="collection-heading"><div class="collection-heading-title">@if($section['cover'])<a class="collection-cover" href="{{ $section['url'] }}" tabindex="-1" aria-hidden="true"><img src="{{ $section['cover']['thumbnail'] ?? $section['cover']['src'] }}" width="96" height="112" alt="" loading="lazy" decoding="async"></a>@endif<h2><a href="{{ $section['url'] }}">{{ $section['collection']->title }}</a></h2></div><a class="text-button" href="{{ $section['url'] }}">Shop {{ $section['collection']->title }}</a></div>
        <div class="product-cards">@foreach($section['cards'] as $card) @include('products.card',['priority'=>!$hero && $loop->parent->first && $loop->first]) @endforeach</div></section>
    @endforeach
    @if($sections->isEmpty() && $fallbackCards->isNotEmpty())
        <section class="featured-collection"><div class="collection-heading"><h2>Explore our products</h2><a href="{{ route('shop') }}">View all products</a></div><div class="product-cards">@foreach($fallbackCards as $card) @include('products.card',['priority'=>!$hero && $loop->first]) @endforeach</div></section>
    @endif
    <p><a class="text-button" href="{{ route('shop') }}">Browse all products</a></p>
</main>
@endsection
