@can('manage-commerce')
@php($analytics = $this->analytics())
@php($trafficTotal = max(1, array_sum(array_column($analytics['daily'], 'views'))))
<section class="sf-analytics" aria-label="Store performance">
    <header><h2>Store performance &mdash; last 14 days</h2><p>Page requests, not unique people. Orders include every status and are not paid revenue. Dates use Pakistan time.</p></header>
    @unless($analytics['available'])<p role="status">Traffic tracking is waiting for its database migration. Order counts are available now.</p>@endunless
    <div class="sf-chart-grid">
        @foreach(['views' => 'Public page requests', 'orders' => 'Orders received'] as $metric => $title)
        @php($maximum = max(1, max(array_column($analytics['daily'], $metric))))
        <section class="sf-chart"><header class="sf-chart-heading"><h3>{{ $title }}</h3><div><strong>{{ array_sum(array_column($analytics['daily'], $metric)) }}</strong><small>14-day total</small></div></header>
        <div class="sf-daily-bars" role="list" aria-label="{{ $title }} by day">
        @foreach($analytics['daily'] as $date => $counts)<div role="listitem" aria-label="{{ $date }}: {{ $counts[$metric] }}" title="{{ $date }}: {{ $counts[$metric] }}"><span class="sf-day-value">{{ $counts[$metric] > 0 ? $counts[$metric] : '' }}</span><div class="sf-bar-track"><i style="height:{{ round($counts[$metric] / $maximum * 100) }}%"></i></div><small>{{ \Carbon\CarbonImmutable::parse($date)->format('d') }}<br>{{ \Carbon\CarbonImmutable::parse($date)->format('M') }}</small></div>@endforeach
        </div></section>
        @endforeach
    </div>
    <div class="sf-audience-grid">
        @foreach(['source'=>'Referring websites', 'page'=>'Most viewed public pages'] as $dimension=>$title)
        <section class="sf-chart"><h3>{{ $title }}</h3>@include('filament.analytics-breakdown', ['rows' => $analytics['groups'][$dimension] ?? []])</section>
        @endforeach
        <section class="sf-chart sf-environment"><h3>Devices and browsers</h3>
        @foreach(['device'=>'Device type', 'platform'=>'Operating system', 'browser'=>'Browser'] as $dimension=>$title)
        <section><h4>{{ $title }}</h4>@include('filament.analytics-breakdown', ['rows' => $analytics['groups'][$dimension] ?? []])</section>
        @endforeach
        </section>
    </div>
    <details class="sf-analytics-info"><summary>About these statistics and privacy</summary><p>Counts update at most once a minute. Percentages are shares of all page requests in this 14-day period; each list shows up to eight values. Device and browser categories are inferred from browser headers and may be inaccurate. Referral data is the referring website, not a person's location or identity. Missing referrers appear as Direct / unknown. Admin, private shopping pages, known bots and Do Not Track / Global Privacy Control requests are excluded. No visitor IDs, IP addresses, raw browser headers or query strings are saved.</p></details>
</section>
@endcan
