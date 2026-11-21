<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\StoreSettings;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PackingSlipController extends Controller
{
    public function __invoke(Order $order): Response
    {
        Gate::authorize('view', $order);
        $order->load(['lines' => fn ($query) => $query->orderBy('id')]);

        return response()->view('shop.packing-slip', ['order' => $order, 'brand' => StoreSettings::brandName()])
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
