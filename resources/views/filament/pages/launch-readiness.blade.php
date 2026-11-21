<x-filament-panels::page>
    <p>Advisory snapshot. Ready means configured, not independently verified or approved. Viewing this page never publishes content, changes stock/prices, enables checkout or sends email. Reload to refresh.</p>
    @foreach(['Needs configuration','Manual check','Ready'] as $status)
    <x-filament::section :heading="$status">
        <div class="space-y-5">@forelse(collect($findings)->where('status',$status) as $finding)
            <article><h3 class="font-semibold">{{ $finding['title'] }}</h3><p>{{ $finding['detail'] }}</p>@if($finding['url'])<a class="underline" href="{{ $finding['url'] }}">Open {{ $finding['title'] }} settings</a>@endif</article>
        @empty<p>No findings in this group.</p>@endforelse</div>
    </x-filament::section>
    @endforeach
    <p>For individual colors and sizes, open a product's Storefront readiness summary. It separates missing PKR prices, unconfigured stock and normal sold-out sizes, with links to existing controls.</p>
</x-filament-panels::page>
