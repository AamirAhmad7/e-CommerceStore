<x-filament-panels::page>
    <p>Active sizes in published products and active categories. Stock includes reservations. A missing photo does not block checkout.</p>
    <div style="display:flex;gap:1rem;flex-wrap:wrap">
        <x-filament::button tag="a" :href="\App\Filament\Pages\CatalogAttention::getUrl(['issue' => 'stock'])" :color="$issue === 'stock' ? 'primary' : 'gray'">Needs stock</x-filament::button>
        <x-filament::button tag="a" :href="\App\Filament\Pages\CatalogAttention::getUrl(['issue' => 'price'])" :color="$issue === 'price' ? 'primary' : 'gray'">In stock, missing PKR price</x-filament::button>
    </div>
    @if($issue === 'missing_price')<p>Showing all active sizes missing a positive PKR checkout price, including sizes that also need stock.</p>@endif
    {{ $this->table }}
</x-filament-panels::page>
