<x-filament-panels::page>
    <form wire:submit="save" class="sf-admin-settings">
        <x-filament::section>
            <div>
                <label for="brand-name">Store name</label>

                <x-filament::input.wrapper :valid="! $errors->has('brandName')">
                    <x-filament::input
                        id="brand-name"
                        type="text"
                        wire:model="brandName"
                        maxlength="120"
                        required
                        aria-describedby="brand-name-hint brand-name-error"
                    />
                </x-filament::input.wrapper>

                <p id="brand-name-hint">Appears in the public header, footer, page titles and social previews. Your homepage hero headline is edited separately in Homepage.</p>
                <div id="brand-name-error" aria-live="polite">
                    @error('brandName')
                        <p>{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6">
                <label for="print-logo">Logo for printed documents</label>
                <input id="print-logo" type="file" wire:model="printLogo" accept="image/png,image/jpeg,image/webp" aria-describedby="print-logo-help">
                <p id="print-logo-help">PNG, JPG or WebP, up to 2 MB and 4000 pixels per side. Save to use it on address labels, invoices and picking documents. Until uploaded, a sample SF monogram is used.</p>
                <p wire:loading wire:target="printLogo">Uploading logo...</p>
                @error('printLogo')<p role="alert">{{ $message }}</p>@enderror
                @if(\App\Support\StoreSettings::printLogoUrl())<img src="{{ \App\Support\StoreSettings::printLogoUrl() }}" alt="Current print logo" style="max-width:180px;max-height:90px;margin-top:12px;object-fit:contain">@endif
            </div>
            <div class="mt-6">
                <label for="whatsapp-number">WhatsApp number (optional)</label>
                <x-filament::input.wrapper :valid="! $errors->has('whatsappNumber')">
                    <x-filament::input id="whatsapp-number" type="tel" wire:model="whatsappNumber" maxlength="16" aria-describedby="whatsapp-number-hint whatsapp-number-error" />
                </x-filament::input.wrapper>
                <p id="whatsapp-number-hint">International format, for example +923001234567. Include the country code; no spaces or punctuation. Leave blank to hide Ask on WhatsApp.</p>
                <div id="whatsapp-number-error" aria-live="polite">@error('whatsappNumber')<p>{{ $message }}</p>@enderror</div>
            </div>

            <div style="display:grid;gap:1rem;margin-top:2rem">
                <h2>Public information pages</h2>
                <p>Save publishes these words immediately. Use your own story and approved policies. Leave optional address and hours blank to hide them. Contact email is managed in Commerce settings.</p>
                @foreach(['about' => 'About us', 'address' => 'Customer contact address (optional)', 'hours' => 'Support hours (optional)', 'privacy' => 'Privacy notice', 'terms' => 'Ordering terms'] as $key => $label)
                    <label for="page-{{ $key }}">{{ $label }}
                        <textarea id="page-{{ $key }}" wire:model="pages.{{ $key }}" rows="{{ in_array($key, ['address', 'hours']) ? 2 : 5 }}" maxlength="{{ in_array($key, ['address', 'hours']) ? 500 : ($key === 'about' ? 6000 : 12000) }}" style="display:block;width:100%;border:1px solid #aaa;border-radius:8px;padding:.75rem"></textarea>
                        @error('pages.'.$key)<span role="alert">{{ $message }}</span>@enderror
                    </label>
                @endforeach
            </div>
            <x-slot name="footer">
                <x-filament::button type="submit">
                    Save
                </x-filament::button>
            </x-slot>
        </x-filament::section>
    </form>
</x-filament-panels::page>

