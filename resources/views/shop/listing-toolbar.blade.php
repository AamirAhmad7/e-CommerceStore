<div class="catalog-toolbar">
    <p class="catalog-mobile-count">{{ $products->total() }} <span>{{ $products->total() === 1 ? 'item' : 'items' }}</span></p>
    <label class="catalog-sort"><span>Sort by</span><select name="sort" form="catalog-filter-form" aria-label="Sort products" data-catalog-sort>
        <option value="" @selected(empty($filters['sort']))>Recommended</option>
        @if(!empty($filters['q']))<option value="relevance" @selected(($filters['sort'] ?? null) === 'relevance')>Relevance</option>@endif
        @if(isset($collection))<option value="curated" @selected(($filters['sort'] ?? null) === 'curated')>Collection order</option>@endif
        <option value="newest" @selected(($filters['sort'] ?? null) === 'newest')>Newest</option>
        <option value="price_asc" @selected(($filters['sort'] ?? null) === 'price_asc')>Price: low to high</option>
        <option value="price_desc" @selected(($filters['sort'] ?? null) === 'price_desc')>Price: high to low</option>
    </select></label>
    <button type="submit" form="catalog-filter-form" class="text-button catalog-sort-submit" data-sort-submit aria-label="Apply sorting">Go</button>
</div>
