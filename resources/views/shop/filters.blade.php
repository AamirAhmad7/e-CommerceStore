<details class="filter-panel" data-discovery-filters>
<summary>Filter &amp; sort ({{ count($chips) }} active)</summary>
<form method="get" class="shop-filters" action="{{ request()->url() }}">
<label>Search products<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="160" placeholder="Name, category, color or SKU"></label>
@foreach(['category'=>'Category','color'=>'Color','size'=>'Size'] as $key=>$label)
@if($key !== 'category' || !isset($category))
<fieldset><legend>{{ $label }} (choose any)</legend><div class="filter-options">
@php($options = $key === 'category' ? $categories->pluck('name','slug') : ($key === 'color' ? $colors : $sizes)->mapWithKeys(fn($value)=>[$value=>$value]))
@foreach(collect($filters[$key] ?? [])->diff($options->keys()) as $missing) @php($options[$missing]=$missing) @endforeach
@foreach($options as $value=>$labelText)<label><input type="checkbox" name="{{ $key }}[]" value="{{ $value }}" @checked(in_array((string)$value,$filters[$key] ?? [],true))> {{ $labelText }}</label>@endforeach
</div></fieldset>
@endif
@endforeach
<label class="stock-choice"><input type="checkbox" name="in_stock" value="1" @checked($filters['in_stock'] ?? false)> In stock and ready to buy</label>
<label>Currency<select name="currency"><option @selected($currency==='PKR')>PKR</option><option @selected($currency==='USD')>USD</option></select></label>
<input type="hidden" name="price_currency" value="{{ $currency }}">
<label>Minimum price ({{ $currency }})<input name="min" inputmode="decimal" value="{{ $filters['min'] ?? '' }}"></label>
<label>Maximum price ({{ $currency }})<input name="max" inputmode="decimal" value="{{ $filters['max'] ?? '' }}"></label>
<p class="filter-hint">Changing currency clears the price range. USD is for browsing; checkout uses PKR.</p>
<label>Sort<select name="sort"><option value="" @selected(empty($filters['sort']))>Recommended{{ isset($collection) ? ' (collection order without search)' : '' }}</option>
@if(!empty($filters['q']))<option value="relevance" @selected(($filters['sort'] ?? null)==='relevance')>Relevance</option>@endif
@if(isset($collection))<option value="curated" @selected($sort==='curated')>Collection order</option>@endif
<option value="newest" @selected($sort==='newest')>Newest</option><option value="price_asc" @selected($sort==='price_asc')>Price: low to high</option><option value="price_desc" @selected($sort==='price_desc')>Price: high to low</option></select></label>
<button class="button">Apply filters</button><a class="text-button" href="{{ $clearUrl }}">Clear all</a>
</form></details>
<div class="browse-summary"><p>{{ $products->total() }} {{ $products->total()===1?'product':'products' }}</p><nav class="active-filters" aria-label="Active filters">@foreach($chips as $chip)<a href="{{ $chip['url'] }}" aria-label="Remove {{ $chip['label'] }}">{{ $chip['label'] }} <span aria-hidden="true">&times;</span></a>@endforeach</nav></div>
