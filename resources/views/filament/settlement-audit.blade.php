<div>
    <h3>Statement {{ $line->settlement->external_reference }} / {{ $line->settlement->courier->name }}</h3>
    <p>Payout date: {{ $line->settlement->payout_date->format('Y-m-d') }}. Reference: {{ $line->settlement->bank_reference }}. Posted by staff #{{ $line->settlement->actor_id }} at {{ $line->settlement->created_at }}.</p>
    <p>Discrepancy review: {{ $line->settlement->review_note ?: 'No discrepancy at posting' }}</p>
    <p>Tracking: {{ $line->tracking_number }}. Confirmed customer collection: {{ App\Services\Pricing::format($line->collection_minor) }}.</p>
    <p>Original gross: {{ App\Services\Pricing::format($line->gross_minor) }}. Original fee: {{ App\Services\Pricing::format($line->fee_minor) }}. Original net: {{ App\Services\Pricing::format($line->net_minor) }}.</p>
    @php($balance = $line->balances())
    <p>After corrections: gross {{ App\Services\Pricing::format($balance['gross']) }}, fee {{ App\Services\Pricing::format($balance['fee']) }}, net {{ App\Services\Pricing::format($balance['net']) }}. Net unresolved: {{ App\Services\Pricing::format($balance['difference']) }}.</p>
    <h4>Append-only adjustments</h4>
    @forelse(Illuminate\Support\Facades\DB::table('courier_settlement_adjustments')->where('courier_settlement_line_id', $line->id)->orderBy('id')->get() as $adjustment)
        <p>{{ $adjustment->created_at }} / staff #{{ $adjustment->actor_id }} / {{ $adjustment->reference }}: gross change {{ App\Services\Pricing::format($adjustment->gross_delta) }}, fee change {{ App\Services\Pricing::format($adjustment->fee_delta) }}, net change {{ App\Services\Pricing::format($adjustment->net_delta) }}. {{ $adjustment->reason }}</p>
    @empty<p>No corrections.</p>@endforelse
</div>
