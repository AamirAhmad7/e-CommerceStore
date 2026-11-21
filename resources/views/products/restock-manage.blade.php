@extends('layouts.store')
@section('title','Manage size alert | '.\App\Support\StoreSettings::brandName())
@section('meta')<meta name="robots" content="noindex, nofollow, noarchive">@endsection
@section('content')
<main id="product" class="product-shell"><h1>Manage your size alert</h1>
@if($public)<p>{{ \App\Services\RestockAlerts::label($variant) }}</p>@endif
@if($subscription->cancelled_at)<p>This request is cancelled.</p>
@elseif($subscription->completed_at)<p>Your one-time alert has been sent. It does not reserve stock.</p>
@elseif($subscription->confirmed_at)<p>Your alert is confirmed. We will email you once when this exact size is available to order.</p>
@elseif($subscription->confirmation_expires_at->isPast())<p>The confirmation link expired. You can request a new link from the product page.</p>
@else<p>Press Confirm alert to request one back-in-stock email. Opening this page does not confirm or cancel anything.</p>@endif
@if(!$subscription->cancelled_at && !$subscription->completed_at)
<form method="post" action="{{ route('restock.update',$token) }}">@csrf
@if($canConfirm)<button class="button" name="action" value="confirm">Confirm alert</button>@endif
<button class="button-secondary" name="action" value="cancel">Cancel this alert</button>
</form>@endif
@if($public)<p><a class="text-button" href="{{ \App\Services\RestockAlerts::buyUrl($variant) }}">View this exact color and size</a></p>@endif
</main>
@endsection
