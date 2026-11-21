@php($units=0) @php($known=0) @php($missing=0)
@php($labels=App\Models\ProductVariant::whereIn('id',collect($rows)->pluck('variant_id')->filter())->pluck('sku','id'))
<div><h3>Purchase totals</h3>
@foreach($rows as $row)
@php($qty=is_numeric($row['quantity'] ?? null)?(int)$row['quantity']:0)
@php($cost=preg_match('/^\d{1,8}(?:\.\d{1,2})?$/',(string)($row['cost'] ?? ''))?App\Services\Pricing::parse((string)$row['cost'],'PKR'):null)
@php($units+=$qty) @php($known+=($cost ?? 0)*$qty) @php($missing+=$cost===null?1:0)
<p>{{ $labels[$row['variant_id'] ?? 0] ?? 'Choose SKU' }} / {{ $qty }} units / unit {{ $cost===null?'Unknown':App\Services\Pricing::format($cost) }} / line {{ $cost===null?'Unknown':App\Services\Pricing::format($cost*$qty) }}</p>
@endforeach
<strong>{{ $units }} units / {{ $missing?'Known-cost subtotal (incomplete)':'Merchandise total' }}: {{ App\Services\Pricing::format($known) }}</strong>
@if($missing)<p>{{ $missing }} lines need costs before issue. Unknown is not zero.</p>@endif
<p>Freight estimate: {{ filled($freight)?'PKR '.$freight:'Not entered' }} (separate from merchandise).</p>
</div>