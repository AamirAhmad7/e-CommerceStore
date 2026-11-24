@extends('layouts.store')
@section('title', ($preview ? 'Preview: ' : '').$seoTitle)
@section('meta')
@if($preview)<meta name="robots" content="noindex, nofollow, noarchive">@endif
<meta property="og:type" content="product"><meta property="og:title" content="{{ $seoTitle }}"><meta property="og:description" content="{{ $metaDescription }}"><meta property="og:url" content="{{ $canonical }}">
@if($socialPhoto)<meta property="og:image" content="{{ $socialPhoto['src'] }}"><meta property="og:image:alt" content="{{ $socialPhoto['alt'] }}"><meta property="og:image:width" content="{{ $socialPhoto['width'] }}"><meta property="og:image:height" content="{{ $socialPhoto['height'] }}">@endif
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
                
                @include('products.save', ['saveProduct' => $product])
                @unless($preview)<div class="product-share"><button class="button-secondary" type="button" data-copy-product aria-describedby="share-help" hidden>Copy link to share</button><p id="share-help" class="share-help" data-share-help hidden>Share this jacket with your selected color and size.</p><p data-share-status role="status"></p><input data-share-fallback aria-label="Product link: select and copy" readonly hidden></div>@endunless
                <div class="selection-block">
                    <h2>Color <span id="color-name">{{ $selected['name'] ?? 'No colors listed' }}</span></h2>
                    @if($colorUnavailable)<p role="status">That color is not currently offered. Choose a color and an available size.</p>@endif
                    <nav class="colors" aria-label="Choose a color">
                        @foreach($colors as $color)
                            @if(!$preview && !collect($color['variants'])->contains('can_buy', true))
                            <button type="button" class="color-option color-unavailable" disabled aria-label="{{ $color['name'] }}  -  unavailable">
                                <span class="swatch" @if($color['swatch']) style="background-color: {{ $color['swatch'] }}" @endif aria-hidden="true"></span>
                                <span><s>{{ $color['name'] }}</s><small>{{ $color['status'] ?: 'Unavailable' }}</small></span>
                            </button>
                            @else
                            <a href="{{ $color['url'] }}" data-color="{{ $color['id'] }}" class="color-option" @if($selected && $selected['id'] === $color['id']) aria-current="true" @endif>
                                <span class="swatch" @if($color['swatch']) style="background-color: {{ $color['swatch'] }}" @endif aria-hidden="true"></span>
                                <span>{{ $color['name'] }}@if($color['status']) <small>{{ $color['status'] }}</small>@endif</span>
                            </a>
                            @endif
                        @endforeach
                    </nav>
                </div>
                <div class="selection-block">
                    <h2 class="size-heading">Size <span id="size-name">{{ $selectedVariant['label'] ?? 'Choose a size.' }}</span></h2>
                    @if($measurementGuide || count($guides)) @include('products.size-guide') @endif
                    @if($sizeUnavailable)<p id="unavailable-size" role="status">That size is currently unavailable. Please choose an available size.</p>@endif
                    <nav id="sizes" class="sizes" aria-label="Choose a size">
                        @forelse($selected['variants'] ?? [] as $variant)
                            @if($variant['can_buy'])
                            <a href="{{ $selected['url'].'&'.http_build_query(['size' => $variant['size']]) }}" data-size="{{ $variant['size'] }}" @if($selectedVariant && $selectedVariant['id'] === $variant['id']) aria-current="true" @endif>{{ $variant['label'] }}</a>
                            @else<button type="button" disabled aria-label="{{ $variant['label'].' - '.$variant['disabled_label'] }}"><span>{{ $variant['label'] }}</span> <small>{{ $variant['disabled_label'] }}</small></button>@endif
                        @empty
                            <p>No sizes are currently offered for this color.</p>
                        @endforelse
                    </nav>
                    @include('products.restock-form')
                    
                    <p class="product-delivery-note">{{ \App\Services\CommerceSettings::productDeliveryDescription($product, $currency) }}</p>
                    
                    <p id="selected-stock" class="stock-note" role="status">{{ $selectedVariant['availability'] ?? ($preview && empty($selected['variants']) ? 'No active sizes. Activate a size in the product form.' : ($selected['availability'] ?? 'No sizes are currently available to order. Browse other products or contact the store.')) }}</p>
                    @if(!$preview)
                    @if($currency === 'USD' || \App\Services\CommerceSettings::checkoutReady($product))
                    <form action="{{ route($currency === 'USD' ? 'inquiry.add' : 'cart.add') }}" method="post" id="add-to-cart">@csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <input type="hidden" name="variant_id" id="cart-variant" value="{{ $selectedVariant['id'] ?? '' }}">
                        <input type="hidden" name="currency" value="{{ $currency }}">
                        <input type="hidden" name="expected_price" value="{{ $selectedVariant['price'] ?? '' }}">
                        <label>Quantity<input type="number" name="quantity" value="1" min="1" max="{{ min(100, $selectedVariant['available'] ?? 0) }}" @disabled(!($selectedVariant['can_buy'] ?? false)) required></label>
                        <button class="button" id="cart-submit" @disabled(!($selectedVariant['can_buy'] ?? false))>{{ $currency === 'USD' ? 'Add to inquiry' : 'Add to cart' }}</button>
                    </form>@else<p><a class="button" href="{{ route('information.contact') }}">Contact us to order</a></p>@endif
                    @if($whatsappUrl && $currency === 'PKR')
                        <p><a id="ask-whatsapp" class="whatsapp-button whatsapp-secondary" href="{{ $selectedVariant['whatsapp_url'] ?? $selected['whatsapp_url'] ?? $whatsappUrl }}" target="_blank" rel="noopener noreferrer">@include('shop.whatsapp-icon') Ask on WhatsApp</a></p>
                    @endif
                    @endif
                    <p id="variant-status" class="variant-status" aria-live="polite">{{ $selectedVariant ? 'Selected: '.$selected['name'].' / '.$selectedVariant['label'].' · SKU '.$selectedVariant['sku'] : '' }}</p>
                </div>
                <p class="trust-line">Cash on delivery &middot; <a href="{{ route('information.contact') }}">Store support</a></p><p class="service-link"><a href="{{ route('policies') }}">Delivery &amp; returns</a></p>
                
                @if($product->description || collect(['material', 'lining', 'closure', 'fit', 'care'])->contains(fn ($field) => filled($product->$field)))
                    <section class="description" aria-labelledby="details-heading"><h2 id="details-heading">Details</h2><p>{{ $product->description }}</p><dl class="product-specs">@foreach(['material'=>'Material','lining'=>'Lining','closure'=>'Closure','fit'=>'Fit','care'=>'Care'] as $field=>$label)@if(filled($product->$field))<div><dt>{{ $label }}</dt><dd>{{ $product->$field }}</dd></div>@endif @endforeach</dl></section>
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
        @if($related->isNotEmpty())<section class="related-products"><h2>You may also like</h2><div class="product-cards">@foreach($related as $card)@include('products.card')@endforeach</div></section>@endif
        @if(config('store.delivery_estimate'))<p>{{ config('store.delivery_estimate') }}</p>@endif
    </main>

    @foreach($colors as $color)
        <template data-gallery-template="{{ $color['id'] }}">@include('products.gallery', ['color' => $color])</template>
    @endforeach
    <script type="application/json" id="product-data">{!! Illuminate\Support\Js::encode(['preview' => $preview, 'colors' => $colors, 'selected' => $selected['id'] ?? null, 'size' => $selectedVariant['size'] ?? null]) !!}</script>



@endsection


@if(!$preview && $currency === 'PKR')
@push('overlays')
<div class="mobile-buy-bar" data-mobile-buy hidden><strong data-mobile-price></strong><button type="button" class="button" data-mobile-add>Add to cart</button></div>
<dialog id="added-to-cart" class="cart-confirmation" aria-labelledby="added-cart-title">
    <button type="button" class="button-secondary" data-close-cart>Continue shopping</button>
    <h2 id="added-cart-title">Added to your cart</h2>
    <p data-added-cart-message role="status"></p>
    <a class="button" href="{{ route('checkout.show') }}">Proceed to checkout</a>
    <a class="button-secondary" href="{{ route('cart.show') }}">Review cart</a>
</dialog>
@endpush
@endif
