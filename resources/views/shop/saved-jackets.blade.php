@extends('layouts.store')
@section('content')
<main id="product" class="product-shell home-page saved-jackets">
<h1>Saved jackets</h1>
<p>Your wishlist and recently viewed jackets stay in this browser session. No account needed. Saving does not reserve stock or lock a price.</p>
<div class="product-cards">
@forelse($cards as $card)
@include('products.card')
@empty
<p>No saved jackets yet. Use Save on a jacket you like. <a href="{{ route('shop') }}">Browse jackets</a></p>
@endforelse
</div>
@if($cards->count() > 1)
<form class="comparison-picker" method="get" action="{{ route('saved.show') }}"><fieldset><legend>Choose up to three jackets to compare</legend>
@foreach($cards as $card)<label><input type="checkbox" name="compare[]" value="{{ $card['product']->id }}" @checked(in_array($card['product']->id, $selectedIds))> {{ $card['product']->name }}</label>@endforeach
<button class="button">Compare selected jackets</button></fieldset></form>
@endif
@if($comparison->count() > 1)
<section aria-labelledby="compare-heading"><h2 id="compare-heading">Compare selected jackets</h2>
<p>Prices reflect current listings. Choose a color and size to confirm availability and the exact price.</p>
<div class="jacket-comparison" role="region" aria-label="Jacket comparison" tabindex="0">
<table><caption>Jacket details supplied by the store</caption><thead><tr><th scope="col">Detail</th>@foreach($comparison as $card)<th scope="col"><a href="{{ $card['url'] }}">{{ $card['product']->name }}</a></th>@endforeach</tr></thead>
<tbody>
<tr><th scope="row">Price</th>@foreach($comparison as $card)<td>{{ $card['price'] }}</td>@endforeach</tr>
@foreach(['material' => 'Material', 'lining' => 'Lining', 'fit' => 'Fit', 'closure' => 'Closure / hardware', 'care' => 'Care'] as $field => $label)
<tr><th scope="row">{{ $label }}</th>@foreach($comparison as $card)<td>{{ $card['product']->$field ?: 'Not provided' }}</td>@endforeach</tr>
@endforeach
<tr><th scope="row">Available sizes</th>@foreach($comparison as $card)<td>{{ $card['product']->colors->flatMap(fn ($color) => $color->variants)->filter(fn ($variant) => \App\Services\Inventory::purchasable($variant, $currency, $card['product']))->pluck('size')->unique()->sortBy(fn ($size) => \App\Support\ProductPresentation::sizeKey($size))->map(fn ($size) => \App\Support\ProductPresentation::sizeLabel($size))->implode(', ') ?: 'Currently unavailable' }}</td>@endforeach</tr>
</tbody></table></div></section>
@endif
<section aria-labelledby="recent-heading"><h2 id="recent-heading">Recently viewed</h2>
<form method="post" action="{{ route('saved.clear') }}">@csrf @method('DELETE')<button class="button-secondary">Clear viewing history</button></form>
<div class="product-cards">@forelse($recentCards as $card) @include('products.card') @empty <p>No other recently viewed jackets.</p> @endforelse</div>
</section>
<a href="{{ route('orders.find') }}">Find my order</a>
</main>
@endsection
