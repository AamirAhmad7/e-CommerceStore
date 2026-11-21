<x-filament-panels::page>
    <p>Edit and save a draft, then preview it at mobile and desktop widths. Customers keep seeing the published version until you choose Publish homepage. Unpublished or empty featured collections are omitted automatically.</p>
    <form wire:submit="saveDraft" class="space-y-6">
        {{ $this->form }}
        <div class="flex flex-wrap gap-4">
            <x-filament::button type="submit">Save draft</x-filament::button>
            <x-filament::button tag="a" href="{{ route('home.preview') }}" target="_blank" color="gray">Preview saved draft</x-filament::button>
            <x-filament::button type="button" wire:click="publish" wire:confirm="Save these edits and publish this homepage?">Publish homepage</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
