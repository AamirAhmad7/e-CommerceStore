<div class="sf-order-alerts" wire:poll.30s.visible aria-label="Order alerts">
    <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('index', ['filters' => ['status' => ['value' => 'pending']]]) }}">Orders to review <strong>{{ $pending }}</strong></a>
    @if($cancellations)<a class="sf-cancel-alert" href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('index', ['filters' => ['pending_cancellations' => ['isActive' => true]]]) }}">Cancellation requests <strong>{{ $cancellations }}</strong></a>@endif
    @if($corrections)<a class="sf-cancel-alert" href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('index', ['filters' => ['address_requests' => ['isActive' => true]]]) }}">Address corrections <strong>{{ $corrections }}</strong></a>@endif
    @if($failedMessages)<a class="sf-cancel-alert" href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('index', ['filters' => ['failed_messages' => ['isActive' => true]]]) }}">Failed emails <strong>{{ $failedMessages }}</strong></a>@endif
</div>
