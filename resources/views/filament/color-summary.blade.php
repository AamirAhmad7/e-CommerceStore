<div class="sf-color-summary" style="--sf-color-accent: {{ $swatch }}">
    <span class="sf-color-swatch" style="background-color: {{ $swatch }}" aria-hidden="true"></span>
    <span><strong>{{ $name }}</strong><span class="sf-row-caption">{{ $saved ? 'Saved color overview' : 'New color — save to add stock' }}</span></span>
    <span class="sf-color-counts">
        <span><b>{{ $sizes }}</b> {{ $sizes === 1 ? 'size' : 'sizes' }}</span>
        <span><b>{{ $available }}</b> available to order</span>
        <span><b>{{ $photos }}</b> {{ $photos === 1 ? 'photo' : 'photos' }}</span>
    </span>
</div>
