@php($base=App\Models\PurchaseOrder::query()->when($productId,fn($q)=>$q->whereHas('lines.variant.productColor',fn($c)=>$c->where('product_id',$productId)))->when($supplierId,fn($q)=>$q->where('supplier_id',$supplierId)))
@php($awaiting=(clone $base)->whereIn('status',App\Models\PurchaseOrder::OPEN)->count())
@php($overdue=(clone $base)->whereIn('status',App\Models\PurchaseOrder::OPEN)->whereDate('expected_at','<',today())->count())
@php($reorder=App\Services\Replenishment::query()->when($productId,fn($q)=>$q->whereHas('productColor',fn($c)=>$c->where('product_id',$productId)))->when($supplierId,fn($q)=>$q->where('supplier_id',$supplierId))->count())
@php($recent=App\Models\PurchaseReceipt::when($productId,fn($q)=>$q->whereHas('lines.orderLine.variant.productColor',fn($c)=>$c->where('product_id',$productId)))->where('created_at','>=',now()->subDays(30))->when($supplierId,fn($q)=>$q->whereHas('order',fn($p)=>$p->where('supplier_id',$supplierId)))->count())
<div style="padding:1rem"><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(10rem,1fr));gap:.75rem">
@foreach([
[App\Filament\Pages\Replenishment::getUrl(['product'=>$productId,'supplier'=>$supplierId]),$reorder,'Reorder needed / SKUs'],
[App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::getUrl('index',['product'=>$productId,'supplier'=>$supplierId,'scope'=>'awaiting']),$awaiting,'Awaiting delivery / orders'],
[App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::getUrl('index',['product'=>$productId,'supplier'=>$supplierId,'scope'=>'overdue']),$overdue,'Overdue / orders'],
[App\Filament\Pages\PurchaseReceipts::getUrl(['product'=>$productId,'supplier'=>$supplierId,'recent'=>1]),$recent,'Receipt entries / last 30 days']
] as [$url,$count,$label])<a href="{{ $url }}" style="padding:1rem;border:1px solid #8886;border-radius:.5rem"><strong style="font-size:1.4rem">{{ $count }}</strong><br>{{ $label }}</a>@endforeach
</div><nav style="display:flex;flex-wrap:wrap;gap:1rem;margin-top:1rem">@foreach(['awaiting'=>'Awaiting delivery','draft'=>'Drafts (not incoming)','overdue'=>'Overdue','all'=>'All purchase orders'] as $value=>$label)<a href="{{ App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::getUrl('index',['product'=>$productId,'supplier'=>$supplierId,'scope'=>$value]) }}">{{ $label }}</a>@endforeach</nav></div>