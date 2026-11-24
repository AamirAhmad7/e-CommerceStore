<article class="sf-attention-card {{ $item['count'] > 0 ? 'has-work' : 'is-clear' }}">
    <a href="{{ $item['url'] }}"><span class="sf-attention-count">{{ $item['count'] }}</span><span>{{ $item['label'] }}</span>@if($item['count'] === 0)<small>{{ $item['group'] }} &middot; Nothing pending</small>@endif</a>
    @if($item['label'] === 'Courier parcels needing follow-up')
        <details class="sf-card-help"><summary aria-label="About courier follow-up">i</summary><p>No recorded update for three days. This is a reminder to contact the courier, not a promised delivery date or live tracking.</p></details>
    @endif
</article>
