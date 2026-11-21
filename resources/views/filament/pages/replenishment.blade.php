<x-filament-panels::page>
<p><a href="{{ App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::getUrl() }}">Purchasing overview</a> ? <a href="{{ App\Filament\Resources\SupplierSources\SupplierSourceResource::getUrl() }}">Supplier mappings and quotes</a></p>
<label><input type="checkbox" wire:model.live="showAll">Show all active SKUs, including missing reorder settings</label>
<p>A SKU needs review when available + incoming is at or below its reorder point. Drafts do not count as incoming. Zero is a real threshold; blank is not configured. Suggestions round the target gap up to the preferred supplier's minimum and pack size. No positive gap means zero suggested units.</p>
{{ $this->table }}
</x-filament-panels::page>