@can('manage-commerce')
@php($analytics = $this->analytics())
<section class="sf-analytics" aria-label="Store performance">
    <header><h2>Store performance &mdash; last 14 days</h2><p>Page requests, not unique people. Orders include every status and are not paid revenue. Updated at most once a minute; dates use Pakistan time.</p></header>
    @unless($analytics['available'])<p role="status">Traffic tracking is waiting for its database migration. Order counts are available now.</p>@endunless
    <div class="sf-chart-grid">
        @foreach(['views' => 'Public page requests', 'orders' => 'Orders received'] as $metric => $title)
        @php($maximum = max(1, max(array_column($analytics['daily'], $metric))))
        <section class="sf-chart"><h3>{{ $title }} <strong>{{ array_sum(array_column($analytics['daily'], $metric)) }}</strong></h3>
        <div class="sf-daily-bars" role="list" aria-label="{{ $title }} by day">
        @foreach($analytics['daily'] as $date => $counts)<div role="listitem" aria-label="{{ $date }}: {{ $counts[$metric] }}" title="{{ $date }}: {{ $counts[$metric] }}"><span>{{ $counts[$metric] }}</span><div class="sf-bar-track"><i style="height:{{ max(1, round($counts[$metric] / $maximum * 100)) }}%"></i></div><small>{{ substr($date, 8) }}</small></div>@endforeach
        </div></section>
        @endforeach
    </div>
    <div class="sf-chart-grid">
    @foreach(['source'=>'Referring websites', 'page'=>'Most viewed public pages', 'device'=>'Device type', 'platform'=>'Operating system', 'browser'=>'Browser'] as $dimension=>$title)
        <section class="sf-chart"><h3>{{ $title }}</h3>
        @php($rows = $analytics['groups'][$dimension] ?? [])
        @php($maximum = max(1, max(array_column($rows, 'count') ?: [0])))
        @forelse($rows as $row)<div class="sf-meter-row"><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong><meter min="0" max="{{ $maximum }}" value="{{ $row['count'] }}" aria-label="{{ $row['label'] }}: {{ $row['count'] }} page requests"></meter></div>@empty<p>No traffic recorded yet.</p>@endforelse
        </section>
    @endforeach
    </div>
    <p>Device and browser categories are inferred from browser headers and may be inaccurate. Referral data is the referring website, not a person's location or identity. Missing referrers appear as Direct / unknown. Admin, private shopping pages, known bots and Do Not Track / Global Privacy Control requests are excluded. No visitor IDs, IP addresses, raw browser headers or query strings are saved.</p>
</section>
@endcan
