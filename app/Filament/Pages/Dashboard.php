<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\Dispatching;
use App\Support\ProductWorkspace;
use App\Support\StorefrontAnalytics;
use Illuminate\Support\Facades\Gate;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected string $view = 'filament.pages.dashboard';

    public function analytics(): array
    {
        Gate::authorize('manage-commerce');

        return StorefrontAnalytics::summary();
    }

    public function attention(): array
    {
        if (! Gate::allows('manage-commerce')) {
            return [];
        }

        return [
            ['group' => 'Orders', 'label' => 'Address corrections to review', 'count' => Order::whereHas('requests', fn ($q) => $q->where('type', 'address')->where('status', 'pending'))->count(), 'url' => OrderResource::getUrl('index', ['filters' => ['address_requests' => ['isActive' => true]]])],
            ['group' => 'Catalog', 'label' => 'Sizes below reorder level', 'count' => \App\Services\Replenishment::query()->count(), 'url' => Replenishment::getUrl()],
            ['group' => 'Delivery', 'label' => 'Courier parcels needing follow-up', 'count' => Order::where('status', 'shipped')->whereHas('shipment', fn ($q) => $q->where('stage', 'with_courier')->where('updated_at', '<', now()->subDays(3)))->count(), 'url' => Dispatch::getUrl(['stage' => 'follow_up'])],
            ['group' => 'Orders', 'label' => 'New orders to confirm', 'count' => Order::where('status', 'pending')->count(), 'url' => OrderResource::getUrl('index', ['filters' => ['status' => ['value' => 'pending']]])],
            ['group' => 'Orders', 'label' => 'Cancellation requests', 'count' => Order::whereHas('requests', fn ($q) => $q->where('type', 'cancellation')->where('status', 'pending'))->count(), 'url' => OrderResource::getUrl('index', ['filters' => ['pending_cancellations' => ['isActive' => true]]])],
            ['group' => 'Delivery', 'label' => 'Packed: assign to courier', 'count' => Order::whereHas('shipment', fn ($q) => $q->where('stage', 'packed'))->where('status', 'processing')->count(), 'url' => Dispatch::getUrl(['stage' => 'packed'])],
            ['group' => 'Delivery', 'label' => 'Delivery issues', 'count' => Order::whereHas('shipment', fn ($q) => $q->where('stage', 'issue'))->where('status', 'shipped')->count(), 'url' => Dispatch::getUrl(['stage' => 'issue'])],
            ['group' => 'Communication', 'label' => 'Failed customer emails', 'count' => Order::whereHas('events', fn ($q) => $q->whereNull('sent_at')->whereNotNull('last_error')->where('recipient', '!=', ''))->count(), 'url' => OrderResource::getUrl('index', ['filters' => ['failed_messages' => ['isActive' => true]]])],
            ['group' => 'Communication', 'label' => 'Emails waiting over 15 minutes', 'count' => Order::whereHas('events', fn ($q) => $q->whereNull('sent_at')->whereNull('last_error')->where('recipient', '!=', '')->where('created_at', '<', now()->subMinutes(15)))->count(), 'url' => OrderResource::getUrl('index', ['filters' => ['waiting_messages' => ['isActive' => true]]])],
            ['group' => 'Orders', 'label' => 'Bank proofs awaiting review', 'count' => Order::where('payment_status', 'proof_submitted')->count(), 'url' => OrderResource::getUrl('index', ['filters' => ['payment_status' => ['value' => 'proof_submitted']]])],
            ['group' => 'Orders', 'label' => 'Orders ready to pack', 'count' => Dispatching::readyToPack()->count(), 'url' => Dispatch::getUrl(['stage' => 'packable'])],
            ['group' => 'Orders', 'label' => 'Orders with pending returns', 'count' => Order::whereHas('requests', fn ($query) => $query->where('type', 'return')->where('status', 'pending'))->count(), 'url' => OrderResource::getUrl('index', ['filters' => ['pending_returns' => ['isActive' => true]]])],
            ['group' => 'Catalog', 'label' => 'Sizes needing stock', 'count' => ProductWorkspace::attention('stock')->count(), 'url' => CatalogAttention::getUrl(['issue' => 'stock'])],
            ['group' => 'Catalog', 'label' => 'In stock, missing PKR price', 'count' => ProductWorkspace::attention('price')->count(), 'url' => CatalogAttention::getUrl(['issue' => 'price'])],
        ];
    }
}
