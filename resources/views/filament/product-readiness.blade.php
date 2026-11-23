<div class="sf-readiness">
    <p class="sf-save-state" x-data x-text="window.jsMd5(JSON.stringify($wire.data).replace(/\\/g, '')) !== $wire.savedDataHash ? 'Pending changes - Save changes to apply product edits.' : 'No pending product changes.'" role="status"></p>
    <p class="sf-save-help">Save changes applies your form edits. Price and stock dialogs save separately when confirmed.</p>
    @if(!$findings)
        <p>Save this product to enter stock and prices.</p>
    @else
        <p class="sf-publication-status"><strong>{{ $findings[0]['text'] }}</strong></p>
        <details class="sf-checklist">
            <summary>Review setup ({{ collect($findings)->where('kind', 'Configuration')->count() }} setup issues)</summary>
            @foreach(collect($findings)->where('kind', 'Configuration')->groupBy(fn ($finding) => $finding['action'] ?? 'publication') as $action => $issues)
                <details>
                    <summary>{{ $issues->count() }} {{ match($action) { 'managePrices' => 'sizes need a PKR price', 'manageStock' => 'sizes have never had stock entered', default => 'publication or size settings need attention' } }}</summary>
                    <ul>@foreach($issues as $finding)<li>{{ $finding['text'] }}
                        @if(isset($finding['action']))<x-filament::button size="sm" color="gray" type="button" wire:click="mountAction('{{ $finding['action'] }}', { variant: {{ $finding['variant'] }} })">{{ $finding['label'] }}</x-filament::button>@endif
                    </li>@endforeach</ul>
                </details>
            @endforeach
            @foreach(['Normal stock state' => 'Sold out / reserved sizes (not a setup error)', 'Optional improvement' => 'Optional photos and presentation'] as $kind => $heading)
                @if(collect($findings)->contains('kind', $kind))<details><summary>{{ $heading }}</summary><ul>@foreach(collect($findings)->where('kind', $kind) as $finding)<li>{{ $finding['text'] }}</li>@endforeach</ul></details>@endif
            @endforeach
        </details>
    @endif
</div>
