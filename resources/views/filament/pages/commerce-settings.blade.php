<x-filament-panels::page>
<p role="status">Store-default checkout: <strong>{{ \App\Services\CommerceSettings::checkoutReady() ? 'Live - customers can order' : 'Default not ready - check delivery and payment settings below' }}</strong>. Products with their own delivery charge can be ordered when a payment method is enabled. Unsaved changes do not affect the storefront.</p>
<p>Product delivery charges: Catalog &gt; Products &gt; Edit product &gt; Pricing. Enter the charge once for all colors and sizes. Blank uses the store default below; 0 means free. Checkout charges the highest applicable rate once per order, subject to the free-delivery threshold.</p>
<form wire:submit="save" class="sf-admin-settings space-y-6">
<x-filament::section heading="International inquiry shipping (USD)">
<p>These terms apply to inquiries only. They never change Pakistan checkout or convert selling prices. Countries without a separate rate require a quote.</p>
<label>Shipping terms<select wire:model="data.international_shipping_mode"><option value="quote">Request a shipping quote</option><option value="included">Included in advertised USD prices</option><option value="separate">Charged separately by destination</option></select></label>
@foreach($data['international_shipping_rates'] ?? [] as $index => $rate)
<div wire:key="international-rate-{{ $index }}" style="display:flex;gap:1rem;flex-wrap:wrap;margin:1rem 0">
<label>Country name<input wire:model="data.international_shipping_rates.{{ $index }}.destination" placeholder="United Kingdom"></label>
<label>USD estimate<input inputmode="decimal" wire:model="data.international_shipping_rates.{{ $index }}.amount"></label>
<label>Charge once per<select wire:model="data.international_shipping_rates.{{ $index }}.basis"><option value="order">Order</option><option value="item">Jacket</option></select></label>
<x-filament::button color="gray" type="button" wire:click="removeInternationalRate({{ $index }})">Remove rate</x-filament::button>
</div>@endforeach
<x-filament::button color="gray" type="button" wire:click="addInternationalRate">Add destination rate</x-filament::button>
@foreach($errors->getMessages() as $key => $messages)@if(str_starts_with($key, 'data.international_'))<p role="alert">{{ implode(' ', $messages) }}</p>@endif @endforeach
<p>Rates are staged until you save the rules below. Included shipping adds zero extra charge. An order rate applies once to the entire inquiry.</p>
</x-filament::section>
    <x-filament::section heading="Pakistan checkout rules">
        <p>Choose Free delivery or a Fixed delivery charge and enable a payment method. Once saved, checkout becomes available automatically. Until then, shoppers can browse and contact you to order. No additional tax is charged.</p>
<label for="delivery-mode">Delivery charges</label>
<x-filament::input.wrapper><x-filament::input.select id="delivery-mode" wire:model.live="deliveryMode"><option value="unconfigured">Not configured - checkout unavailable</option><option value="free">Free delivery</option><option value="fixed">Fixed delivery charge</option></x-filament::input.select></x-filament::input.wrapper>
@error('deliveryMode')<p role="alert">{{ $message }}</p>@enderror
@if($deliveryMode === 'free')<p>Customers pay no delivery charge. This takes effect when you save below.</p>@endif

        @foreach(['shipping_name'=>'Delivery method name','shipping_minor'=>'Shipping rate (PKR)','free_shipping_minor'=>'Free shipping from (PKR, optional)','expiry_hours'=>'Unpaid bank transfer expiry (hours)'] as $key=>$label)
        @continue(in_array($key, ['shipping_minor', 'free_shipping_minor'], true) && $deliveryMode !== 'fixed')
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

