@extends('layouts.store')
@section('title', ($preview ? 'Preview: ' : '').$product->name.' | '.$brand)
@section('meta')
@if($preview)<meta name="robots" content="noindex, nofollow, noarchive">@endif
<meta property="og:type" content="product"><meta property="og:title" content="{{ $product->name }} | {{ $brand }}"><meta property="og:description" content="{{ $metaDescription }}"><meta property="og:url" content="{{ $canonical }}">
@if($socialPhoto)<meta property="og:image" content="{{ $socialPhoto['src'] }}"><meta property="og:image:alt" content="{{ $socialPhoto['alt'] }}">@endif
@endsection
@section('content')
    @if($preview)
        <aside class="preview-banner" role="note"><strong>Admin preview</strong> Saved changes only. This preview is private; viewing it does not publish the product.</aside>
    @endif
    <main id="product" class="product-shell">
        <p class="breadcrumb"><a href="{{ route('shop') }}">Shop</a> / @if($product->category?->is_active)<a href="{{ route('categories.show',$product->category->slug) }}">{{ $product->category->name }}</a>@else{{ $product->category?->name }}@endif</p>
        <div class="product-grid">
            <div class="product-intro"><p class="eyebrow">{{ $brand }}</p><h1 id="product-title">{{ $product->name }}</h1></div>
            <section class="gallery-column" aria-label="Selected color gallery">
                <div id="color-gallery">@include('products.gallery', ['color' => $selected])</div>
            </section>
            <section class="product-details" aria-labelledby="product-title">


                <p id="selected-price">{{ $selectedVariant['price_label'] ?? ($selected['price_label'] ?? 'Currently unavailable') }}</p>
                <nav class="currency-nav" aria-label="Browsing currency"><a href="{{ request()->fullUrlWithQuery(['currency'=>'PKR']) }}">PKR</a> / <a href="{{ request()->fullUrlWithQuery(['currency'=>'USD']) }}">USD</a></nav>
                <div class="selection-block">
                    <h2>Color <span id="color-name">{{ $selected['name'] ?? 'No colors listed' }}</span></h2>
                    @if($colorUnavailable)<p class="offering-note">That color is not currently offered. Showing an available selection.</p>@endif
                    <nav class="colors" aria-label="Choose a color">
                        @foreach($colors as $color)
                            <a href="{{ $color['url'] }}" data-color="{{ $color['id'] }}" class="color-option" @if($selected && $selected['id'] === $color['id']) aria-current="true" @endif>
                                <span class="swatch" @if($color['swatch']) style="background-color: {{ $color['swatch'] }}" @endif aria-hidden="true"></span>
                                <span>{{ $color['name'] }}@if($color['status']) <small>{{ $color['status'] }}</small>@endif</span>
                            </a>
                        @endforeach
                    </nav>
                </div>
                <div class="selection-block">
                    <h2>Size <span id="size-name">{{ $selectedVariant['label'] ?? 'Choose a size.' }}</span></h2>
                    @if($measurementGuide || count($guides)) @include('products.size-guide') @endif
                    @if($sizeUnavailable)<p id="unavailable-size" role="status">That size is currently unavailable. Please choose an available size.</p>@endif
                    <nav id="sizes" class="sizes" aria-label="Choose a size">
                        @forelse($selected['variants'] ?? [] as $variant)
                            @if($variant['can_buy'])
                            <a href="{{ $selected['url'].'&'.http_build_query(['size' => $variant['size']]) }}" data-size="{{ $variant['size'] }}" @if($selectedVariant && $selectedVariant['id'] === $variant['id']) aria-current="true" @endif>{{ $variant['label'] }}</a>
                            @else<button type="button" disabled aria-label="{{ $variant['label'].' - '.$variant['disabled_label'] }}"><s>{{ $variant['label'] }}</s> <small>{{ $variant['disabled_label'] }}</small></button>@endif
                        @empty
                            <p>No sizes are currently offered for this color.</p>
                        @endforelse
                    </nav>
                    @include('products.restock-form')
                    <p class="offering-note">Pakistan checkout charges PKR.</p>
                    
                    <p id="selected-stock" role="status">{{ $selectedVariant['availability'] ?? ($preview && empty($selected['variants']) ? 'No active sizes. Activate a size in the product form.' : ($selected['availability'] ?? 'No sizes are currently available to order. Browse other products or contact the store.')) }}</p>
                    @if(!$preview)
                    <form action="{{ route('cart.add') }}" method="post" id="add-to-cart">@csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <input type="hidden" name="variant_id" id="cart-variant" value="{{ $selectedVariant['id'] ?? '' }}">
                        <label>Quantity<input type="number" name="quantity" value="1" min="1" max="{{ min(100, $selectedVariant['available'] ?? 0) }}" @disabled(!($selectedVariant['can_buy'] ?? false)) required></label>
                        <button class="button" id="cart-submit" @disabled(!($selectedVariant['can_buy'] ?? false))>Add to cart</button>
                    </form>
                    @if($whatsappUrl)
                        <p><a id="ask-whatsapp" class="text-button" href="{{ $selectedVariant['whatsapp_url'] ?? $selected['whatsapp_url'] ?? $whatsappUrl }}" target="_blank" rel="noopener noreferrer">Ask on WhatsApp</a></p>
                    @endif
                    @endif
                    <p id="variant-status" class="variant-status" aria-live="polite">{{ $selectedVariant ? 'Selected: '.$selected['name'].' / '.$selectedVariant['label'].' · SKU '.$selectedVariant['sku'] : '' }}</p>
                </div>
                <p class="service-link">Pakistan checkout in PKR. Delivery charges are shown at checkout. <a href="{{ route('policies') }}">Delivery &amp; returns</a></p>
                @if(collect(['material','lining','fit','care'])->contains(fn($field)=>filled($product->$field)))
                    <details class="product-information"><summary>Material, fit &amp; care</summary><dl>@foreach(['material'=>'Material','lining'=>'Lining','fit'=>'Fit','care'=>'Care'] as $field=>$label)@if(filled($product->$field))<dt>{{ $label }}</dt><dd class="preserve-lines">{{ $product->$field }}</dd>@endif @endforeach</dl></details>
                @endif
                @if($product->description)
                    <section class="description" aria-labelledby="details-heading"><h2 id="details-heading">Details</h2><p>{{ $product->description }}</p></section>
                @endif
            </section>
        </div>
        @if(count($shared))
            <section class="shared-gallery" aria-labelledby="shared-details-heading">
                <h2 id="shared-details-heading">Product details</h2>
                <div class="shared-grid">
                @foreach($shared as $photo)
                    <figure>
                        <a href="{{ $photo['original'] }}" target="_blank" rel="noopener">
                            <img src="{{ $photo['src'] }}" @if($photo['srcset']) srcset="{{ $photo['srcset'] }}" @endif sizes="(min-width: 900px) 60vw, 100vw" width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" alt="{{ $photo['alt'] }}" loading="lazy">
                        </a>
                        @if(filled($photo['alt']))<figcaption>{{ $photo['alt'] }}</figcaption>@endif
                    </figure>
                @endforeach
                </div>
            </section>
        @endif
    </main>

    @foreach($colors as $color)
        <template data-gallery-template="{{ $color['id'] }}">@include('products.gallery', ['color' => $color])</template>
    @endforeach
    <script type="application/json" id="product-data">{!! Illuminate\Support\Js::encode(['preview' => $preview, 'colors' => $colors, 'selected' => $selected['id'] ?? null, 'size' => $selectedVariant['size'] ?? null]) !!}</script>



@endsection
