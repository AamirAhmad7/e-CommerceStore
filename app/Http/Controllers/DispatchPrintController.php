<?php

namespace App\Http\Controllers;

use App\Models\DispatchManifest;
use App\Models\Order;
use App\Services\CourierSettlements;
use App\Services\Dispatching;
use App\Support\StoreSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DispatchPrintController extends Controller
{
    public function picking(Request $request): Response
    {
        Gate::authorize('manage-commerce');
        $data = $request->validate(['orders' => 'required|array|min:1|max:100', 'orders.*' => 'required|integer|distinct']);
        $orders = Order::with('lines')->whereIn('id', $data['orders'])->orderBy('id')->get();
        abort_unless($orders->count() === count($data['orders']), 404);
        $items = [];
        foreach ($orders as $order) {
            Gate::authorize('view', $order);
            if ($reason = Dispatching::blocked($order)) {
                throw ValidationException::withMessages(['dispatch' => $order->number.': '.$reason]);
            }
            foreach ($order->lines as $line) {
                $items[$line->sku] ??= ['product' => $line->product_name, 'color' => $line->color, 'size' => $line->size, 'quantity' => 0, 'orders' => []];
                $items[$line->sku]['quantity'] += $line->quantity;
                $items[$line->sku]['orders'][] = $order->number.' x '.$line->quantity;
            }
        }
        ksort($items);

        return response()->view('shop.dispatch-print', ['brand' => StoreSettings::brandName(), 'items' => $items, 'manifest' => null, 'title' => 'Combined picking list']);
    }

    public function manifest(DispatchManifest $manifest): Response
    {
        Gate::authorize('manage-commerce');
        $manifest->load('shipments.order');

        return response()->view('shop.dispatch-print', ['brand' => StoreSettings::brandName(), 'items' => [], 'manifest' => $manifest, 'title' => 'Dispatch manifest '.$manifest->reference]);
    }

    public function template(): StreamedResponse
    {
        Gate::authorize('verify-payments');

        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, CourierSettlements::HEADER, escape: '');
            fclose($stream);
        }, 'courier-statement-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
