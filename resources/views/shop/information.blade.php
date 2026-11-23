@extends('layouts.store')
@section('content')
<main id="product" class="product-shell information-page">
    <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a> / {{ $title }}</nav>
    <header class="information-heading"><p class="eyebrow">{{ \App\Support\StoreSettings::brandName() }}</p><h1>{{ $title }}</h1></header>
    <div class="information-grid"><article class="information-copy">
    @if($page === 'about')
        @if(filled($pages['about']))<p class="preserve-lines">{{ $pages['about'] }}</p>@else
            <h2>Find a jacket that feels like you.</h2><p>Explore our jackets by color, size and fit. Each product page brings together the available sizes, color photographs and product details so you can make a considered choice.</p>
            <p>Need help choosing? Contact us with the product name and the size you are considering.</p>
        @endif
        <a class="button" href="{{ route('shop') }}">Explore the collection</a>
    @elseif($page === 'contact')
        <h2>How can we help?</h2><p>For sizing, product questions or an existing order, get in touch using the details below. Include your order reference if you have one. Never send a password or payment-card details.</p>
        <div class="contact-methods">
            @if($whatsapp)<a class="whatsapp-button" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener noreferrer">@include('shop.whatsapp-icon') Chat on WhatsApp</a>@endif
            @if(filled($settings['contact_email']))<a class="contact-email" href="mailto:{{ $settings['contact_email'] }}">Email us <span>{{ $settings['contact_email'] }}</span></a>@endif
        </div>
        @if(filled($pages['hours']))<h3>Support hours</h3><p class="preserve-lines">{{ $pages['hours'] }}</p>@endif
        @if(filled($pages['address']))<h3>Contact address</h3><p class="preserve-lines">{{ $pages['address'] }}</p>@endif
        @if(!$whatsapp && !filled($settings['contact_email']))<p>Direct contact details have not been published yet. For an existing order, use the secure order lookup below.</p>@endif
        <a class="text-button" href="{{ route('orders.find') }}">Find my order</a>
    @elseif($page === 'faq')
        <p>Answers to the questions that come up before and after ordering.</p>
        @foreach([
            'How do I choose a size?' => 'Open a product and look for Size & fit beside the sizes. When measurements are supplied, compare them with your body or a jacket that fits, following the method shown. Color photos are shared by all sizes of that color.',
            'Can I buy directly from the collection?' => 'Use Choose size on a product card, select a color and an available size, then Add to cart. You can also open the full product page for more details.',
            'Which currency will I pay in?' => 'All orders are charged in PKR and delivered within Pakistan. Review your total before confirming.',
            'What happens if my size is sold out?' => 'Sold-out sizes cannot be added to the cart. Where Notify me is available, you can request an alert for that exact color and size.',
            'How do I find an existing order?' => 'Use Find my order with your order reference and the email address used at checkout. The recovery link is private; do not share it.',
            'Where do I enter a promotion code?' => 'Enter your code in the PKR cart or at checkout and press Apply. The order summary shows whether it applies and the resulting discount.'
        ] as $question => $answer)<details class="help-question"><summary>{{ $question }}</summary><p>{{ $answer }}</p></details>@endforeach
        <details class="help-question"><summary>Which payment methods are available?</summary><p>@if($settings['cod_enabled'])Cash on delivery is enabled. @endif @if($settings['bank_enabled'] && filled($settings['bank_instructions']))Manual bank transfer is enabled; follow the instructions supplied with your order. @endif Available methods are confirmed at checkout. A bank-transfer order is not marked paid until the store verifies payment.</p></details>
        <details class="help-question"><summary>What are the delivery and returns arrangements?</summary><p>Review the current <a class="text-button" href="{{ route('policies') }}">Delivery &amp; returns information</a>. Delivery charges are confirmed at checkout.</p></details>
    @elseif($page === 'privacy')
        @if(config('store.analytics_enabled'))<h2>Anonymous page statistics</h2><p>We count public page requests by day, referring website, and broad device, operating-system and browser categories. We do not store IP addresses, visitor identifiers, raw browser headers, search terms or private order links in these statistics. These counts are not unique visitor counts. Do Not Track and Global Privacy Control requests are excluded. Aggregate statistics are scheduled for removal after 90 days.</p>@endif
        @if(filled($pages['privacy']))<p class="preserve-lines">{{ $pages['privacy'] }}</p>@else
            @if(config('store.marketing_enabled'))<h2>Optional shopping analytics</h2><p>With your permission, Google Analytics and Meta can receive product views, cart additions, checkout and order-value events. These events do not include your name, contact details, address or private order-access link. Use Analytics preferences to change your choice. Browser Do Not Track and Global Privacy Control signals are respected.</p>@endif<h2>Your shopping information</h2><p>The store uses session cookies and an encrypted 30-day cart cookie to keep your shopping choices, and security tokens to protect submitted forms. Ordering requires contact and delivery details so your order can be processed.</p>
            <p>Order lookup uses your reference and email address. Keep order access links private. If you choose a restock alert, your email is used for that request and the confirmation message includes a way to cancel.</p>
            <p>WhatsApp links open an external service. Information you choose to send there is handled by that service as well as the store. Contact us with questions about your information.</p>
        @endif
    @elseif($page === 'terms')
        @if(filled($pages['terms']))<p class="preserve-lines">{{ $pages['terms'] }}</p>@else
            <h2>Before confirming your order</h2><p>Check the selected product, color, size, quantity and delivery details. Checkout shows your items, delivery charge, discounts and final PKR total before confirmation.</p>
            <p>Availability and prices are checked again on the server. Adding an item to your cart does not guarantee stock. Available payment methods and any manual-transfer instructions are shown at checkout.</p>
            <p>Read the current delivery and returns information before ordering. Contact the store if you need clarification about a product or an order.</p>
        @endif
    @endif
    </article><aside class="help-links"><h2>Customer care</h2><a href="{{ route('orders.find') }}">Find my order <span aria-hidden="true">&rarr;</span></a><a href="{{ route('policies') }}">Delivery &amp; returns</a><a href="{{ route('information.faq') }}">Help &amp; FAQs</a><a href="{{ route('information.contact') }}">Contact us</a></aside></div>
</main>
@endsection
