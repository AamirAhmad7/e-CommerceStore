@extends('layouts.store')
@section('title','Delivery & returns | '.\App\Support\StoreSettings::brandName())
@section('content')
<main id="product" class="product-shell policy-page">
    <p class="eyebrow">Customer information</p><h1 class="page-heading">Delivery &amp; returns</h1>
    <section><h2>Delivery within Pakistan</h2><p>We deliver within Pakistan. All prices and order totals are in PKR.</p>
    <p>Enter your delivery details and review your cart at checkout to see the applicable delivery charge and total before placing your order.</p>
    @if(filled($settings['delivery_information']))<p class="preserve-lines">{{ $settings['delivery_information'] }}</p>@else<p>Delivery details have not been published yet. Please contact the store before ordering if you need a delivery estimate.</p>@endif</section>
    <section><h2>Returns</h2>
    @if($settings['returns_enabled'] && $settings['returns_policy_approved'] && filled($settings['returns_information']))
        <p>Return requests may be submitted within {{ $settings['return_days'] }} days of recorded delivery, subject to the conditions below. A request is reviewed by the store; submission does not automatically approve a return or refund.</p>
        <p class="preserve-lines">{{ $settings['returns_information'] }}</p>
    @else<p>A public return window is not currently offered. Contact the store about your item before ordering. Existing orders can be found using Find my order.</p>@endif
    </section>
    @if(filled($settings['contact_email']))<section><h2>Contact the store</h2><a href="mailto:{{ $settings['contact_email'] }}">{{ $settings['contact_email'] }}</a></section>@endif
    <p><a class="text-button" href="{{ route('shop') }}">Continue shopping</a></p>
</main>
@endsection
