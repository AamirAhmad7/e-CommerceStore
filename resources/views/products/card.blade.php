<article class="product-card"><a href="{{ $card['url'] }}">
@if($card['photo'])
    @php($photo=$card['photo'])
    <img src="{{ $photo['src'] }}" @if($photo['srcset']) srcset="{{ $photo['srcset'] }}" @endif sizes="(min-width:1440px) 310px, (min-width:1120px) 23vw, (min-width:800px) 30vw, (min-width:360px) 46vw, 92vw" width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" alt="{{ $photo['alt'] }}" loading="{{ ($priority??false) ? 'eager' : 'lazy' }}" @if($priority??false) fetchpriority="high" @endif decoding="async">
@else<div class="card-empty">Photo coming soon</div>@endif
<div class="card-info"><h2>{{ $card['product']->name }}</h2>
@if($card['color'])<p class="card-color">{{ $card['color']->name }}</p>@endif
@if(isset($card['detail']))<p class="card-detail">{{ $card['detail'] }}</p>@endif
<p class="card-price">{{ $card['price'] }}</p>
<span class="card-action" aria-hidden="true">View product <span>&rarr;</span></span></div>
</a></article>
