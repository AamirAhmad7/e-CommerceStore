@php($brand = $brand ?? \App\Support\StoreSettings::brandName())
<!DOCTYPE html><html lang="en"><head>
<meta name="referrer" content="strict-origin"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', $seoTitle ?? ($title ?? 'Shop').' | '.$brand)</title>
@unless(request()->routeIs('assisted.*', 'cart.*', 'checkout.*', 'orders.*', 'inquiry.*'))<meta property="og:site_name" content="{{ $brand }}">@endunless
<meta name="description" content="{{ $metaDescription ?? 'Explore the '.$brand.' catalog, colors and sizes.' }}">
@if(isset($canonical))<link rel="canonical" href="{{ $canonical }}">@endif
@if(!\App\Support\StorefrontSeo::indexingAllowed() || ($noindex ?? false) || request()->is('cart*','checkout*','orders/*'))<meta name="robots" content="noindex, nofollow">@endif
@yield('meta')
@if(isset($structuredData))<script type="application/ld+json">{!! \Illuminate\Support\Js::encode($structuredData) !!}</script>@endif
@if(request()->routeIs('home') && !($preview ?? false))<script type="application/ld+json">{!! \Illuminate\Support\Js::encode(\App\Support\StorefrontSeo::organization()) !!}</script>@endif
@vite(['resources/css/app.css','resources/js/app.js'])
</head><body class="product-page" id="top">
<a class="skip-link" href="#product">Skip to content</a>
<div class="store-header-frame"><header class="store-header" data-store-header>
<a class="wordmark" href="{{ route('home') }}" aria-label="{{ $brand }} home">{{ $brand }}</a>
@unless(request()->routeIs('checkout.*', 'assisted.show'))<nav class="store-nav" id="store-menu" aria-label="Main navigation">
<a href="{{ route('shop') }}" @if(request()->routeIs('shop')) aria-current="page" @endif>Shop</a>
<details class="nav-browse"><summary>Browse jackets</summary><div class="nav-browse-links">@foreach(\App\Support\StoreNavigation::links() as $destination)<a href="{{ $destination['url'] }}" @if($destination['active']) aria-current="page" @endif>{{ $destination['label'] }}</a>@endforeach</div></details>
<a href="{{ route('saved.show') }}">Saved jackets</a>
<a href="{{ route('information.faq') }}">Help</a>
<a href="{{ route('orders.find') }}" @if(request()->routeIs('orders.find')) aria-current="page" @endif>Find my order</a>
</nav>@endunless
<a class="header-cart" href="{{ route('cart.show') }}" @if(request()->routeIs('cart.show')) aria-current="page" @endif aria-label="Cart, {{ array_sum(session('cart',[])) }} items">Cart <span class="cart-count">{{ array_sum(session('cart',[])) }}</span></a>
<button class="menu-toggle button-secondary" type="button" aria-controls="store-menu" aria-expanded="false" hidden><span class="menu-icon" aria-hidden="true"></span><span data-menu-label>Menu</span></button>
@unless(request()->routeIs('checkout.*', 'assisted.show'))<details class="header-search" data-header-search><summary aria-label="Search products"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/></svg></summary><form action="{{ route('shop') }}" method="get" role="search" data-suggest-search><label for="header-search">Search products</label><input id="header-search" type="search" data-search-suggest="{{ route('search.suggestions') }}" aria-controls="header-suggestions" autocomplete="off" name="q" maxlength="160" value="{{ is_string(request('q')) ? request('q') : '' }}" placeholder="Name, color or SKU"><input type="hidden" name="currency" value="{{ $currency ?? session('currency','PKR') }}"><button class="button">Search</button>@include('shop.search-suggestions', ['suggestionId' => 'header-suggestions'])</form></details>@endunless
</header></div>
@if(session('status'))<p class="notice" role="status" tabindex="-1" id="shopping-feedback" @if(session('status') === 'Quantity updated.') data-dismiss-after="3000" @endif>{{ session('status') }}</p>@endif
@if($errors->any())<div class="notice error" role="alert" tabindex="-1" id="shopping-feedback"><strong>Please review:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if(request()->routeIs('shop','collections.show','products.show','categories.show','home') && !($preview ?? false) && session('currency', 'PKR') === 'PKR')
@php($promotionMessage=app(\App\Services\Promotions::class)->publicMessage(request()->route('product') instanceof \App\Models\Product ? request()->route('product') : null))
@if($promotionMessage)<aside class="notice" aria-label="Promotion">{{ $promotionMessage }} @if(session('promotion_code'))<span>Code {{ session('promotion_code') }} saved for this session.</span>@endif</aside>@endif
@endif
@yield('content')
@if(request()->routeIs('shop', 'categories.show', 'collections.show', 'home', 'products.show', 'saved.show') && !($preview ?? false)) @include('products.quick-add') @endif
@if(request()->routeIs('checkout.*', 'assisted.show'))
<footer class="checkout-footer"><a href="{{ route('policies') }}">Delivery &amp; returns</a><a href="{{ route('information.contact') }}">Contact us</a><a href="{{ route('information.privacy') }}">Privacy notice</a></footer>
@else
@include('shop.footer')
@endif
@stack('overlays')
@include('shop.commerce-events')
</body></html>
