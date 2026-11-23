<x-filament-panels::page>
    <h2 style="font-size:1.25rem;font-weight:600">What needs attention today?</h2>
    <p>Open a count to see the matching records. Reload this page after making changes.</p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr));gap:1rem">
        @foreach($this->attention() as $item)
            <x-filament::section>
                <a href="{{ $item['url'] }}" style="display:block;text-decoration:underline;padding:.5rem">
                    <strong style="display:block;font-size:2rem">{{ $item['count'] }}</strong>{{ $item['label'] }}
                </a>
            </x-filament::section>
        @endforeach
    </div>
@include('filament.analytics')
</x-filament-panels::page>

