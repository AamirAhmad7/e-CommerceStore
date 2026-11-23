<details class="filter-panel" data-discovery-filters open>
    <summary><span class="filter-title"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 7h16M4 17h16M8 4v6m8 4v6"/></svg> Filters @if(count($chips))<span class="filter-count">{{ count($chips) }}</span>@endif</span></summary>
    <form method="get" id="catalog-filter-form" class="shop-filters" action="{{ request()->url() }}">
        <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
        @foreach(['category' => 'Category', 'color' => 'Color', 'size' => 'Size'] as $key => $label)
            @if($key !== 'category' || !isset($category))
                @php($options = $key === 'category' ? $categories->pluck('name', 'slug') : ($key === 'color' ? $colors : $sizes)->mapWithKeys(fn ($value) => [$value => $key === 'size' ? \App\Support\ProductPresentation::sizeLabel($value) : $value]))
                @foreach(collect($filters[$key] ?? [])->diff($options->keys()) as $missing) @php($options[$missing] = $missing) @endforeach
                @if($options->isNotEmpty())<fieldset class="filter-group"><legend>{{ $label }}</legend><div class="filter-options filter-chips">
                    @foreach($options as $value => $labelText)<label><input type="checkbox" name="{{ $key }}[]" value="{{ $value }}" @checked(in_array((string) $value, $filters[$key] ?? [], true))><span>{{ $labelText }}</span></label>@endforeach
                </div></fieldset>@endif
            @endif
        @endforeach
        <fieldset class="filter-group"><legend>Price range <span>({{ $currency }})</span></legend><div class="price-inputs"><label><span class="filter-sub-label">From</span><input aria-label="Minimum price ({{ $currency }})" name="min" inputmode="decimal" placeholder="Min" value="{{ $filters['min'] ?? '' }}"></label><span aria-hidden="true">&ndash;</span><label><span class="filter-sub-label">To</span><input aria-label="Maximum price ({{ $currency }})" name="max" inputmode="decimal" placeholder="Max" value="{{ $filters['max'] ?? '' }}"></label></div></fieldset>

        <input type="hidden" name="currency" value="PKR">
        <div class="filter-actions"><label class="stock-choice"><input type="checkbox" name="in_stock" value="1" @checked($filters['in_stock'] ?? false)> Only show available sizes</label><div><a class="text-button" href="{{ $clearUrl }}">Reset filters</a><button class="button">Show results</button></div></div>
    </form>
</details>
