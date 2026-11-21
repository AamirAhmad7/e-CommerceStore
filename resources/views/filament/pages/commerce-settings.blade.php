<x-filament-panels::page>
<form wire:submit="save" class="space-y-6">
    <x-filament::section heading="Pakistan checkout rules">
        <p>Enter your own shipping and tax rules. Blank shipping or tax disables checkout; enter 0 explicitly if no charge applies. Prices are explicit PKR, never converted from USD.</p>
        @foreach(['shipping_name'=>'Delivery method name','shipping_minor'=>'Shipping rate (PKR)','free_shipping_minor'=>'Free shipping from (PKR, optional)','tax_basis_points'=>'Tax percentage on merchandise (explicit 0 allowed)','expiry_hours'=>'Unpaid bank transfer expiry (hours)'] as $key=>$label)
        <div class="mt-4"><label for="{{ $key }}">{{ $label }}</label><x-filament::input.wrapper><x-filament::input id="{{ $key }}" wire:model="data.{{ $key }}" /></x-filament::input.wrapper>@error('data.'.$key)<p>{{ $message }}</p>@enderror</div>
        @endforeach
        <label class="block mt-4"><input type="checkbox" wire:model="data.cod_enabled"> Enable cash on delivery</label>
        <label class="block mt-4"><input type="checkbox" wire:model="data.bank_enabled"> Enable manual bank transfer</label>
        <label class="block mt-4" for="bank-instructions">Bank instructions (required to offer bank transfer)</label>
        <textarea id="bank-instructions" wire:model="data.bank_instructions" rows="5" class="w-full border rounded p-3"></textarea>
        @error('data.bank_instructions')<p>{{ $message }}</p>@enderror
        <h2 class="mt-6">Customer service policy</h2>
        <p>Maintain public delivery and returns information here. Do not repeat shipping rates or return-day numbers in prose: those come from the rules below.</p>
        @foreach(['delivery_information'=>'Delivery information (owner-authored)','returns_information'=>'Returns conditions (owner-authored)'] as $field=>$label)
        <label class="block mt-4" for="{{ $field }}">{{ $label }}</label>
        <textarea id="{{ $field }}" wire:model="data.{{ $field }}" rows="4" class="w-full border rounded p-3"></textarea>
        @error('data.'.$field)<p>{{ $message }}</p>@enderror
        @endforeach
        <label class="block mt-4"><input type="checkbox" wire:model="data.returns_policy_approved"> I approve the public returns conditions. Show them and the configured window when return requests are enabled.</label>
        <p>Returns stay disabled until you configure your policy. The window starts when delivery is recorded. Approved requests can still be received after the window closes.</p>
        <label class="block mt-4"><input type="checkbox" wire:model="data.returns_enabled"> Enable customer return requests</label>
        <label class="block mt-4" for="return-days">Return window in days</label>
        <x-filament::input.wrapper><x-filament::input id="return-days" type="number" min="1" max="365" wire:model="data.return_days" /></x-filament::input.wrapper>
        @error('data.return_days')<p>{{ $message }}</p>@enderror
        <label class="block mt-4" for="contact-email">Customer contact email (used in transactional messages)</label>
        <x-filament::input.wrapper><x-filament::input id="contact-email" type="email" wire:model="data.contact_email" /></x-filament::input.wrapper>
        @error('data.contact_email')<p>{{ $message }}</p>@enderror
        <x-filament::button type="submit">Save checkout and service rules</x-filament::button>
    </x-filament::section>
</form>
</x-filament-panels::page>
