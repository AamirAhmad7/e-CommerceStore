@extends('layouts.store')
@section('title', 'Private message preview')
@section('content')
<main id="product" class="product-shell">
    <h1 class="page-heading">{{ $event->subject }}</h1>
    <p>To: {{ $event->recipient }}. {{ $event->sent_at ? 'Processed at '.$event->sent_at : 'Not sent yet' }}.</p>
    <p>This is a staff-only preview of the saved message. Demo messages use log mail and never leave this computer.</p>
    <pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ html_entity_decode($event->body, ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</pre>
    <p><a class="button" href="{{ route('orders.show', $order->access_token) }}">Open private order page</a></p>
</main>
@endsection
