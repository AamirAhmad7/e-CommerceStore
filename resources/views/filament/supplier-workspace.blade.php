<div>
<p>{{ $supplier->contact_person }} / {{ $supplier->city }} / {{ $supplier->is_active?'Active':'Archived / existing orders remain available' }}</p>
<p>Phone: <input readonly value="{{ $supplier->phone }}" aria-label="Copy supplier contact number" style="background:transparent;border:1px solid #888;padding:.5rem"> @if($supplier->whatsappUrl())<a href="{{ $supplier->whatsappUrl() }}" target="_blank" rel="noopener noreferrer">Open WhatsApp</a>@endif / {{ $supplier->email }}</p>
<p>{{ $supplier->notes }}</p>
<h3>Supplied SKUs and costs</h3>
@foreach(App\Filament\Resources\SupplierSources\SupplierSourceResource::sourceQuery()->where('supplier_id',$supplier->id)->limit(10)->get() as $source)<p>{{ $source->variant->sku }} / quote {{ App\Services\Pricing::format($source->quoted_cost_minor) }} ({{ $source->quoted_at?->toDateString() ?? 'not set' }}) / last actual {{ App\Services\Pricing::format($source->last_actual_cost===null?null:(int)$source->last_actual_cost) }} ({{ $source->last_actual_at ?? 'no receipt' }})</p>@endforeach
<a href="{{ App\Filament\Resources\SupplierSources\SupplierSourceResource::getUrl('index',['supplier'=>$supplier->id]) }}">All supplied SKUs / edit quotes</a>
<h3>Open purchases</h3>
@foreach($supplier->orders()->whereIn('status',App\Models\PurchaseOrder::OPEN)->orderBy('expected_at')->limit(10)->get() as $po)<p>{{ $po->reference }} / {{ $po->status }} / expected {{ $po->expected_at?->toDateString() ?? 'not entered' }} @if($po->expected_at?->isBefore(today()))<strong>Overdue</strong>@endif</p>@endforeach
<a href="{{ App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::getUrl('index',['supplier'=>$supplier->id,'scope'=>'awaiting']) }}">All awaiting deliveries</a>
<h3>Recent receipts</h3>
@foreach(App\Models\PurchaseReceipt::whereHas('order',fn($q)=>$q->where('supplier_id',$supplier->id))->latest('id')->limit(5)->get() as $receipt)<p>{{ $receipt->reference }} / {{ $receipt->kind }} / {{ $receipt->created_at }}</p>@endforeach
<a href="{{ App\Filament\Pages\PurchaseReceipts::getUrl(['supplier'=>$supplier->id]) }}">Receipt history and corrections</a>
</div>