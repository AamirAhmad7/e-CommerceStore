<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\StoreSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PackingSlipController extends Controller
{
    public function __invoke(Order $order, Request $request): Response
    {
        Gate::authorize('view', $order);
        $order->load(['lines' => fn ($query) => $query->orderBy('id')]);
        $document = $request->query('document', 'packing-slip');
        abort_unless(in_array($document, ['packing-slip', 'address', 'invoice', 'products'], true), 404);
        $blocked = in_array($order->status, ['cancelled', 'expired', 'returned'], true);
        $cod = $order->payment_method === 'cod' && $order->payment_status !== 'paid' && ! $blocked
            ? max(0, $order->total_minor - max($order->collected_minor, $order->shipment?->collected_minor ?? 0)) : 0;

        return response()->view($document === 'packing-slip' ? 'shop.packing-slip' : 'shop.order-document', ['order' => $order, 'brand' => StoreSettings::brandName(), 'document' => $document, 'cod' => $cod, 'blocked' => $blocked])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
