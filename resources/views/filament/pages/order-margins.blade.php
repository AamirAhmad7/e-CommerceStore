<x-filament-panels::page>
    <x-filament::section heading="Contribution after recorded direct costs">
        <p>Customer collection minus recorded refunds, saved product costs and courier fees. Discounts and customer delivery charges already form part of collection and are not subtracted or added again.</p>
        <p>Resellable returned units reduce product cost; damaged units remain a cost. Missing historical costs or courier fees stay unknown. This is not net profit: rent, salaries, advertising and other overhead are excluded. Courier remittance is tracked separately in Courier Settlements.</p>
        <p>Enter a cost once per product under Catalog &gt; Products &gt; Edit &gt; Pricing. Only new orders save it. No previous order is rewritten.</p>
    </x-filament::section>
    {{ $this->table }}
</x-filament-panels::page>
