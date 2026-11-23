@php($stockTotal = $card['product']->colors->flatMap(fn ($color) => $color->variants)->where('is_active', true)->sum(fn ($variant) => \App\Services\Inventory::available($variant)))
<article class="product-card"><a href="{{ $card['url'] }}">
@if($card['photo'])
    @php($photo=$card['photo'])
    <img src="{{ $photo['src'] }}" @if($photo['srcset']) srcset="{{ $photo['srcset'] }}" @endif sizes="{{ ($listing ?? false) ? '(min-width:2000px) 309px, (min-width:1510px) calc(19.2vw - 59px), (min-width:1175px) calc(24vw - 71px), (min-width:1024px) calc(32vw - 91px), (min-width:667px) calc(32vw - 8px), (min-width:600px) calc(48vw - 6px), (min-width:344px) calc(50vw - 18px), calc(100vw - 24px)' : '(min-width:1440px) 310px, (min-width:1120px) 23vw, (min-width:800px) 30vw, (min-width:360px) 46vw, 92vw' }}" width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" alt="{{ $photo['alt'] ?: $card['product']->name }}" loading="{{ ($priority??false) ? 'eager' : 'lazy' }}" @if($priority??false) fetchpriority="high" @endif decoding="async">
@else<div class="card-empty">Photo coming soon</div>@endif
<div class="card-info">@if($stockTotal === 0)<span class="product-badge">Sold out</span>@elseif($stockTotal === 1)<span class="product-badge stock-note">Last piece</span>@elseif($card['product']->created_at?->greaterThan(now()->subDays(30)))<span class="product-badge">New</span>@endif<h2>{{ $card['product']->name }}</h2>
@if($card['color'])<p class="card-color">{{ $card['color']->name }}</p>@endif
@if(isset($card['detail']))<p class="card-detail">{{ $card['detail'] }}</p>@endif
<p class="card-price">{{ $card['price'] }}</p>
<span class="card-action" aria-hidden="true">View product <span>&rarr;</span></span></div>
</a>
@if(!($preview ?? false))<a class="button button-secondary quick-add-trigger" href="{{ $card['quick_url'] ?? $card['url'] }}" @if(session('currency', 'PKR') !== 'USD' && \App\Services\CommerceSettings::checkoutReady($card['product'])) data-quick-add="{{ route('products.choices', ['product' => $card['product']->slug, 'currency' => $currency ?? session('currency', 'PKR')]) }}" @endif data-quick-color="{{ $card['quick_color'] ?? $card['color']?->id }}" aria-label="Choose size for {{ $card['product']->name }}">{{ session('currency', 'PKR') === 'USD' ? 'Choose size & inquire' : 'Choose size' }}</a>@endif
</article>

