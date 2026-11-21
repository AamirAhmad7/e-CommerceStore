@extends('layouts.store')
@section('title', 'Your private selection | '.App\Support\StoreSettings::brandName())
@section('content')
<main id="product" class="product-shell">
    <h1 class="page-heading">Your private selection</h1>
    @if($state !== 'shared')
        <div class="notice" role="status">
            @if($state === 'expired')This checkout link has expired. Ask the store for a new link.
            @elseif($state === 'revoked')This link was revoked or replaced. Ask the store for the latest selection link.
            @elseif($state === 'converted')This selection has already been ordered. Check your confirmation email for your private order link, or contact the store.
            @else This selection link is unavailable. Ask the store to check the link or send a new one.
            @endif
        </div>
    @else
        <p>{{ $link->sale->reference }} · Stock is confirmed at checkout. Your personal cart stays separate.</p>
        <div class="checkout-grid">
            <section aria-label="Agreed items">
                @foreach($link->snapshot as $line)
                    <article style="margin-bottom:1.5rem">
                        @if($line['photo'])<img src="{{ $line['photo']['src'] }}" alt="{{ $line['photo']['alt'] ?: $line['product'].' — '.$line['color'] }}" width="160" height="160" style="object-fit:contain;max-width:100%">@endif
                        <h2>{{ $line['product'] }}</h2>
                        <p>{{ $line['color'] }} / {{ $line['size'] }} · SKU {{ $line['sku'] }} · Quantity {{ $line['quantity'] }}</p>
                        <p>Shared unit price: {{ App\Services\Pricing::format($line['price']) }} · Line: {{ App\Services\Pricing::format($line['total']) }}</p>
                    </article>
                @endforeach
            </section>
            <section class="order-review">
                <h2>PKR summary</h2>
                <p>Shared merchandise subtotal: <strong>{{ App\Services\Pricing::format($link->subtotal_minor) }}</strong></p>
                @if($quote['subtotal'] !== $link->subtotal_minor)
                    <div class="notice"><p>Prices changed. Current merchandise subtotal: <strong>{{ App\Services\Pricing::format($quote['subtotal']) }}</strong>. You must explicitly accept the revised total at checkout.</p>
                    @foreach($quote['lines'] as $line)<p>{{ $line['variant']?->sku ?? 'Unavailable SKU' }}: {{ $line['price'] === null ? 'Price unavailable' : App\Services\Pricing::format($line['price']).' per unit' }}</p>@endforeach</div>
                @endif
                @if($totals)
                    <p>Delivery ({{ $totals['shipping_name'] }}): {{ App\Services\Pricing::format($totals['shipping_minor']) }}</p>
                    <p>Tax: {{ App\Services\Pricing::format($totals['tax_minor']) }}</p>
                    <p><strong>Current total: {{ App\Services\Pricing::format($totals['total_minor']) }}</strong></p>
                @else<p>Delivery total will be available when the store's delivery rules are configured.</p>@endif
                @if($problem)<p class="notice error" role="alert">{{ $problem }}</p>@endif
                @if(!$problem)
                    <form action="{{ route('assisted.checkout', $link->token) }}" method="post">@csrf<button class="button">Continue to checkout</button></form>
                @endif
                <p>Pakistan delivery. Enter your contact and delivery details on the next screen.</p>
            </section>
        </div>
    @endif
    <p>Need a change? Reply to the store's WhatsApp message or call the person who shared this selection.</p>
</main>
@endsection
