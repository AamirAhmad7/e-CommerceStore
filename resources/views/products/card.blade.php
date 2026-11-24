@php
    $cardCurrency = $currency ?? session('currency', 'PKR');
    $photo = $card['photo'];
    $cardVariants = $card['product']->colors->flatMap(fn ($color) => $color->variants)->where('is_active', true);
    $canChoose = $cardVariants->contains(fn ($variant) => \App\Services\Inventory::purchasable($variant, $cardCurrency, $card['product']));
    $unavailableLabel = $cardVariants->isNotEmpty() && $cardVariants->every(fn ($variant) => $variant->on_hand !== null && \App\Services\Inventory::available($variant) === 0) ? 'Sold out' : 'Currently unavailable';
    $stockTotal = $cardVariants->sum(fn ($variant) => \App\Services\Inventory::available($variant));
    $cardColors = $card['product']->colors->filter(fn ($color) => $color->variants->where('is_active', true)->isNotEmpty());
@endphp
<article class="product-card editorial-card">
    <div class="card-visual">
        <a class="card-photo-link" href="{{ $card['url'] }}" aria-label="View {{ $card['product']->name }}">
        @if($card['photo'])
            <img src="{{ $photo['src'] }}" @if($photo['srcset']) srcset="{{ $photo['srcset'] }}" @endif sizes="{{ ($listing ?? false) ? '(min-width:1920px) 390px, (min-width:1260px) calc(24vw - 71px), (min-width:1024px) calc(32vw - 91px), (min-width:667px) calc(32vw - 8px), (min-width:600px) calc(48vw - 6px), (min-width:344px) calc(50vw - 18px), calc(100vw - 24px)' : '(min-width:1440px) 310px, (min-width:1120px) 23vw, (min-width:800px) 30vw, (min-width:360px) 46vw, 92vw' }}" width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" alt="{{ $photo['alt'] ?: $card['product']->name }}" loading="{{ ($priority ?? false) ? 'eager' : 'lazy' }}" @if($priority ?? false) fetchpriority="high" @endif decoding="async">
        @else<div class="card-empty">Photo coming soon</div>@endif
        </a>
        @if($stockTotal === 0)<span class="product-badge">{{ $cardVariants->every(fn ($variant) => $variant->on_hand !== null) ? 'Sold out' : 'Unavailable' }}</span>
        @elseif($stockTotal === 1)<span class="product-badge stock-note">Last piece</span>
        @elseif($card['product']->created_at?->greaterThan(now()->subDays(30)))<span class="product-badge">New</span>@endif
    </div>
    <div class="card-info">
        <h2><a class="card-title-link" href="{{ $card['url'] }}">{{ $card['product']->name }}</a></h2>
        @if($card['color'])<p class="card-color">Shown: {{ $card['color']->name }}</p>@endif
        @if(isset($card['detail']))<p class="card-detail">{{ $card['detail'] }}</p>@endif
        <p class="card-price">{{ $card['price'] }}</p>
        @if($cardColors->isNotEmpty())
        <nav class="card-swatches" aria-label="Colors for {{ $card['product']->name }}">
            @foreach($cardColors as $color)
                @php
                    $swatch = preg_match('/^#[a-f0-9]{6}$/i', $color->swatch_hex ?? '') ? $color->swatch_hex : (['black' => '#242424', 'brown' => '#795548', 'navy' => '#24354c', 'white' => '#f5f3ed', 'green' => '#35533d'][strtolower($color->name)] ?? '#a6ada5');
                    $availableColor = $color->variants->contains(fn ($variant) => \App\Services\Inventory::purchasable($variant, $cardCurrency, $card['product']));
                @endphp
                @if($availableColor)<a href="{{ route('products.show', ['product' => $card['product']->slug, 'color' => $color->id, 'currency' => $cardCurrency]) }}" aria-label="View {{ $card['product']->name }} in {{ $color->name }}" title="{{ $color->name }}" @if($card['color']?->id === $color->id) aria-current="true" @endif><span style="--swatch: {{ $swatch }}" aria-hidden="true"></span></a>
                @else<span class="card-swatch-unavailable" role="img" aria-label="{{ $color->name }} unavailable" title="{{ $color->name }} unavailable"><span style="--swatch: {{ $swatch }}" aria-hidden="true"></span></span>@endif
            @endforeach
        </nav>
        @endif
    </div>
    @if(!($preview ?? false) && !$canChoose)<button class="card-unavailable" type="button" disabled>{{ $unavailableLabel }}</button>
    @elseif(!($preview ?? false))<a class="button-secondary quick-add-trigger" href="{{ $card['quick_url'] ?? $card['url'] }}" @if($cardCurrency !== 'USD' && \App\Services\CommerceSettings::checkoutReady($card['product'])) data-quick-add="{{ route('products.choices', ['product' => $card['product']->slug, 'currency' => $cardCurrency]) }}" @endif data-quick-color="{{ $card['quick_color'] ?? $card['color']?->id }}" aria-label="Choose size for {{ $card['product']->name }}"><span>{{ $cardCurrency === 'USD' ? 'Choose size & inquire' : 'Choose size' }}</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6"/></svg></a>@endif
    @include('products.save', ['saveProduct' => $card['product']])
</article>

