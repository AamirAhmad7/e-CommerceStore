@php($supportNumber = ($preview ?? false) ? null : \App\Support\StoreSettings::whatsappNumber())
<footer class="store-footer">
    <div class="footer-main">
        <section class="footer-brand"><a class="wordmark" href="{{ route('home') }}">{{ $brand }}</a><p>Your next jacket starts with the right fit.<br>Explore colors, compare sizes, make it yours.</p>@if($supportNumber)<a class="whatsapp-button" href="https://wa.me/{{ $supportNumber }}" target="_blank" rel="noopener noreferrer">@include('shop.whatsapp-icon') Chat on WhatsApp</a>@endif</section>
        <nav aria-label="Shop footer"><h2>Explore</h2><a href="{{ route('shop') }}">Shop all</a>@foreach(\App\Support\StoreNavigation::links() as $link)<a href="{{ $link['url'] }}">{{ $link['label'] }}</a>@endforeach<a href="{{ route('shop', ['sort' => 'newest']) }}">Latest arrivals</a></nav>
        <nav aria-label="Customer care footer"><h2>Customer care</h2><a href="{{ route('orders.find') }}">Find my order</a><a href="{{ route('policies') }}">Delivery &amp; returns</a><a href="{{ route('information.faq') }}">Help &amp; FAQs</a><a href="{{ route('information.contact') }}">Contact us</a></nav>
        <nav aria-label="About footer"><h2>Our store</h2><a href="{{ route('information.about') }}">About us</a><a href="{{ route('information.privacy') }}">Privacy notice</a><a href="{{ route('information.terms') }}">Ordering terms</a></nav>
    </div>
    <div class="footer-bottom"><small>&copy; {{ now()->year }} {{ $brand }}</small><a href="#top">Back to top &uarr;</a></div>
</footer>
@if($supportNumber && !($preview ?? false))<a class="whatsapp-float" href="{{ ($selectedVariant['whatsapp_url'] ?? $selected['whatsapp_url'] ?? $whatsappUrl ?? 'https://wa.me/'.$supportNumber) }}" target="_blank" rel="noopener noreferrer" aria-label="{{ 'Chat with the store on WhatsApp' }}">@include('shop.whatsapp-icon')<span>Need help?</span></a>@endif
