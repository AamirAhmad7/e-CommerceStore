@php($brand = $brand ?? \App\Support\StoreSettings::brandName())
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', ($title ?? 'Shop').' | '.$brand)</title>
<meta property="og:site_name" content="{{ $brand }}">
<meta name="description" content="{{ $metaDescription ?? 'Explore the '.$brand.' catalog, colors and sizes.' }}">
@if(isset($canonical))<link rel="canonical" href="{{ $canonical }}">@endif
@if(($noindex ?? false) || request()->is('cart*','checkout*','orders/*'))<meta name="robots" content="noindex, nofollow">@endif
@yield('meta')
@vite(['resources/css/app.css','resources/js/app.js'])
</head><body class="product-page">
<a class="skip-link" href="#product">Skip to content</a>
<div class="store-context">Pakistan checkout · PKR <span>USD prices are for browsing</span></div>
<header class="store-header" data-store-header>
<a class="wordmark" href="{{ route('home') }}" aria-label="{{ $brand }} home">{{ $brand }}</a>
<nav class="store-nav" id="store-menu" aria-label="Main navigation">
<a href="{{ route('shop') }}" @if(request()->routeIs('shop')) aria-current="page" @endif>Shop</a>
@foreach(\App\Support\StoreNavigation::links() as $destination)<a href="{{ $destination['url'] }}" @if($destination['active']) aria-current="page" @endif>{{ $destination['label'] }}</a>@endforeach
<a href="{{ route('orders.find') }}" @if(request()->routeIs('orders.find')) aria-current="page" @endif>Find my order</a>
</nav>
<a class="header-cart" href="{{ route('cart.show') }}" @if(request()->routeIs('cart.show')) aria-current="page" @endif aria-label="Cart, {{ array_sum(session('cart',[])) }} items">Cart <span class="cart-count">{{ array_sum(session('cart',[])) }}</span></a>
<button class="menu-toggle button-secondary" type="button" aria-controls="store-menu" aria-expanded="false" hidden><span class="menu-icon" aria-hidden="true"></span><span data-menu-label>Menu</span></button>
<details class="header-search" data-header-search open><summary>Search</summary><form action="{{ route('shop') }}" method="get" role="search"><label for="header-search">Search products</label><input id="header-search" type="search" name="q" maxlength="160" value="{{ is_string(request('q')) ? request('q') : '' }}" placeholder="Name, color or SKU"><input type="hidden" name="currency" value="{{ $currency ?? session('currency','PKR') }}"><button class="button">Search</button></form></details>
</header>
@if(session('status'))<p class="notice" role="status" tabindex="-1" id="shopping-feedback">{{ session('status') }}</p>@endif
@if($errors->any())<div class="notice error" role="alert" tabindex="-1" id="shopping-feedback"><strong>Please review:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if(request()->routeIs('shop','collections.show','products.show','categories.show','home') && !($preview ?? false))
@php($promotionMessage=app(\App\Services\Promotions::class)->publicMessage(request()->route('product') instanceof \App\Models\Product ? request()->route('product') : null))
@if($promotionMessage)<aside class="notice" aria-label="Promotion">{{ $promotionMessage }} @if(session('promotion_code'))<span>Code {{ session('promotion_code') }} saved for this session.</span>@endif</aside>@endif
@endif
@yield('content')
<footer class="store-footer"><div><span class="wordmark">{{ $brand }}</span><p>Pakistan checkout in PKR.</p></div><nav aria-label="Footer"><a href="{{ route('shop') }}">Browse the catalog</a><a href="{{ route('policies') }}">Delivery &amp; returns</a><a href="{{ route('orders.find') }}">Find my order</a></nav></footer>
</body></html>
