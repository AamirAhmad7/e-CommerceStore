{{ $brand }}
Order {{ $order->number }}

{{ $messageText }}

Order progress: {{ $order->status }}
Payment progress: {{ $order->payment_status }}

Purchased items:
@foreach($order->lines as $line)
{{ $line->product_name }} / {{ $line->color }} / {{ $line->size }} ({{ $line->sku }}) x {{ $line->quantity }}: {{ \App\Services\Pricing::format($line->total_minor, $line->currency) }}
@endforeach

@if($order->promotion_snapshot)
Promotion: {{ $order->promotion_snapshot['name'] }} {{ $order->promotion_snapshot['code'] ?? '(automatic)' }}
{{ $order->promotion_snapshot['summary'] }}
Merchandise saving: {{ \App\Services\Pricing::format($order->promotion_snapshot['merchandise_discount_minor']) }}
Shipping saving: {{ \App\Services\Pricing::format($order->promotion_snapshot['shipping_discount_minor']) }}
@foreach($order->lines as $line)
{{ $line->sku }} discount: {{ \App\Services\Pricing::format($line->promotion_discount_minor) }}; merchandise paid: {{ \App\Services\Pricing::format($line->total_minor-$line->promotion_discount_minor) }}
@endforeach
@endif
@foreach(['subtotal','discount','tax','shipping','total'] as $field)
{{ ucfirst($field) }}: {{ \App\Services\Pricing::format($order->{$field.'_minor'}, $order->currency) }}
@endforeach
Shipping method: {{ $order->shipping_name }}

View your order and available next steps (private, do not share):
{{ route('orders.show', $order->access_token) }}

@if($contact)
Questions: {{ $contact }}
@endif
