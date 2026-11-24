<x-filament-panels::page>
    @php($attention = collect($this->attention()))
    @php($active = $attention->filter(fn ($item) => $item['count'] > 0)->sortByDesc('count'))
    @php($clear = $attention->filter(fn ($item) => $item['count'] === 0))
    <section class="sf-attention" aria-labelledby="attention-heading">
    <h2 id="attention-heading">What needs attention today?</h2>
    <p>Open a card to see its matching records. Reload after making changes.</p>
    @forelse($active->groupBy('group') as $group => $items)
        <section class="sf-attention-group"><h3>{{ $group }}</h3><div class="sf-attention-grid">
        @foreach($items as $item) @include('filament.attention-card') @endforeach
        </div></section>
    @empty<p class="sf-all-clear">No pending tasks in these queues.</p>@endforelse
    @if($clear->isNotEmpty())
        <details class="sf-clear-tasks"><summary>Nothing pending in {{ $clear->count() }} queues</summary><div class="sf-attention-grid">
        @foreach($clear as $item) @include('filament.attention-card') @endforeach
        </div></details>
    @endif
    </section>
    <h2 class="sf-dashboard-heading">Quick actions</h2>
    <nav class="sf-dispatch-tabs sf-quick-actions" aria-label="Daily operations">
        <a class="fi-btn" href="{{ \App\Filament\Pages\Dispatch::getUrl(['stage' => 'outstanding']) }}">COD awaiting courier remittance</a>
        <a class="fi-btn" href="{{ \App\Filament\Pages\CourierSettlements::getUrl(['differences' => 1]) }}">Reconcile courier differences</a>
        <a class="fi-btn" href="{{ \App\Filament\Pages\Replenishment::getUrl() }}">Plan purchasing</a>
    </nav>
    <details><summary>Download store records</summary><p>Private files contain customer details. Order totals are not profit or collected revenue.</p><nav class="sf-dispatch-tabs"><a class="fi-btn" href="{{ route('store.export', ['dataset' => 'orders']) }}">Export orders CSV</a><a class="fi-btn" href="{{ route('store.export', ['dataset' => 'inventory']) }}">Export inventory CSV</a></nav></details>
    @can('manage-commerce')
    @php($health = collect(\App\Support\OperationsHealth::checks()))
    @php($healthIssues = $health->where('healthy', false)->count())
    <details class="sf-operations-health"><summary><span class="sf-health-dot {{ $healthIssues ? 'needs-attention' : 'healthy' }}" aria-hidden="true"></span>System setup and health <span class="sf-health-count">{{ $healthIssues ? $healthIssues.' checks need attention' : 'Checks up to date' }}</span></summary>
    <x-filament::section heading="Operations health">
        <div class="sf-dispatch-kpis">
        @foreach($health as $check)
            <article class="sf-dispatch-kpi"><h3>{{ $check['title'] }}</h3><p><strong style="font-size:16px">{{ $check['healthy'] ? 'Configured / recent' : 'Needs attention' }}</strong></p><p>{{ $check['detail'] }}</p></article>
        @endforeach
        </div>
        <p>Delivery, restore tests, and live hosting must be verified separately. <a class="underline" href="{{ \App\Filament\Pages\LaunchReadiness::getUrl() }}">Open launch readiness</a></p>
    </x-filament::section>
    </details>
    @endcan
@include('filament.analytics')
</x-filament-panels::page>

