<div class="purchase-grid">
<style>.purchase-grid{overflow-x:auto}.purchase-grid table{border-collapse:collapse;width:100%}.purchase-grid th,.purchase-grid td{padding:.5rem;border-bottom:1px solid #8884}.purchase-grid input{width:5.5rem;border:1px solid #888;border-radius:.3rem;padding:.5rem;background:transparent}.purchase-grid input:focus-visible{outline:3px solid #b45309;outline-offset:2px}.purchase-grid .cell-label{display:none}@media(max-width:640px){.purchase-grid thead{display:none}.purchase-grid tr{display:flex;flex-wrap:wrap;margin-bottom:1rem}.purchase-grid th{width:100%;text-align:left}.purchase-grid td{display:flex;flex-direction:column}.purchase-grid .cell-label{display:block}}</style>
@if($sources->isNotEmpty())
@php($root=Illuminate\Support\Str::beforeLast($getStatePath(),'.'))
@php($sizes=$sources->pluck('variant.size')->unique()->sortBy(fn($size)=>App\Support\ProductPresentation::sizeKey($size)))
<p>Tab between sizes. Blank = skip. Values are sent only when you add the grid.</p>
<table><thead><tr><th>Color / size</th>@foreach($sizes as $size)<th>{{ $size }}</th>@endforeach</tr></thead><tbody>
@foreach($sources->groupBy('variant.productColor.name') as $color=>$group)<tr><th scope="row">{{ $color }}</th>@foreach($sizes as $size)@php($source=$group->first(fn($s)=>$s->variant->size===$size))<td><span class="cell-label">{{ $size }}</span>@if($source)<input type="number" min="1" max="100000" aria-label="{{ $color }} {{ $size }} quantity" wire:model="{{ $root }}.grid.{{ $source->product_variant_id }}"><small>{{ $source->variant->sku }}</small>@else<span>?</span>@endif</td>@endforeach</tr>@endforeach
</tbody></table>
@else<p>Select a supplier and product for the size grid. Add supplier SKU mappings first if no products appear.</p>@endif
</div>