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
            ['label' => 'Bank proofs awaiting review', 'count' => Order::where('payment_status', 'proof_submitted')->count(), 'url' => OrderResource::getUrl('index', ['filters' => ['payment_status' => ['value' => 'proof_submitted']]])],
            ['label' => 'Orders ready to pack', 'count' => Dispatching::readyToPack()->count(), 'url' => Dispatch::getUrl(['stage' => 'packable'])],
            ['label' => 'Orders with pending returns', 'count' => Order::whereHas('requests', fn ($query) => $query->where('type', 'return')->where('status', 'pending'))->count(), 'url' => OrderResource::getUrl('index', ['filters' => ['pending_returns' => ['isActive' => true]]])],
            ['label' => 'Sizes needing stock', 'count' => ProductWorkspace::attention('stock')->count(), 'url' => CatalogAttention::getUrl(['issue' => 'stock'])],
            ['label' => 'In stock, missing PKR price', 'count' => ProductWorkspace::attention('price')->count(), 'url' => CatalogAttention::getUrl(['issue' => 'price'])],
        ];
    }
}
