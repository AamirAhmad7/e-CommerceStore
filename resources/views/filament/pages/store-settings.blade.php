<x-filament-panels::page>
    <form wire:submit="save">
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
                <label for="whatsapp-number">WhatsApp number (optional)</label>
                <x-filament::input.wrapper :valid="! $errors->has('whatsappNumber')">
                    <x-filament::input id="whatsapp-number" type="tel" wire:model="whatsappNumber" maxlength="16" aria-describedby="whatsapp-number-hint whatsapp-number-error" />
                </x-filament::input.wrapper>
                <p id="whatsapp-number-hint">International format, for example +923001234567. Include the country code; no spaces or punctuation. Leave blank to hide Ask on WhatsApp.</p>
                <div id="whatsapp-number-error" aria-live="polite">@error('whatsappNumber')<p>{{ $message }}</p>@enderror</div>
            </div>

            <x-slot name="footer">
                <x-filament::button type="submit">
                    Save
                </x-filament::button>
            </x-slot>
        </x-filament::section>
    </form>
</x-filament-panels::page>
