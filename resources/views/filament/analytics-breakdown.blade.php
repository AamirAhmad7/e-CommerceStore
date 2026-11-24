@forelse($rows as $row)
<div class="sf-meter-row"><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }} <small>({{ round($row['count'] / $trafficTotal * 100, 1) }}%)</small></strong>@if(count($rows) > 1)<meter min="0" max="{{ $trafficTotal }}" value="{{ $row['count'] }}" aria-label="{{ $row['label'] }}: {{ $row['count'] }} page requests"></meter>@endif</div>
@empty<p>No traffic recorded yet.</p>@endforelse
