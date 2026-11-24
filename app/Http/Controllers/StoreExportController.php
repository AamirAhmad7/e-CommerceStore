<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\Inventory;
use App\Services\Pricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoreExportController extends Controller
{
    public function __invoke(Request $request, string $dataset): StreamedResponse
    {
        Gate::authorize('manage-commerce');
        abort_unless(in_array($dataset, ['orders', 'inventory'], true), 404);
        $dates = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'until' => 'nullable|date_format:Y-m-d|after_or_equal:from']);

        return response()->streamDownload(function () use ($dataset, $dates): void {
            $stream = fopen('php://output', 'w');
            $write = static function (array $values) use ($stream): void {
                fputcsv($stream, array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@\-\t\r]/', $value) ? "'".$value : $value, $values), escape: '');
            };
            if ($dataset === 'orders') {
                $write(['Order reference', 'Created', 'Stage', 'Customer', 'Phone', 'City', 'Payment method', 'Payment status', 'Currency', 'Merchandise', 'Discount', 'Shipping', 'Order total', 'Collected', 'Courier', 'Tracking']);
                $query = Order::with('shipment')->when($dates['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))->when($dates['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                foreach ($query->lazyById(200) as $order) {
                    $write([$order->number, $order->created_at->toIso8601String(), $order->stageLabel(), $order->customer['name'] ?? '', $order->customer['phone'] ?? '', $order->customer['city'] ?? '', strtoupper($order->payment_method), $order->payment_status, $order->currency, Pricing::decimal($order->subtotal_minor), Pricing::decimal($order->discount_minor), Pricing::decimal($order->shipping_minor), Pricing::decimal($order->total_minor), Pricing::decimal($order->collected_minor), $order->shipment?->courier_name, $order->shipment?->tracking_number]);
                }
            } else {
                $write(['SKU', 'Product', 'Color', 'Size', 'Active', 'Physical stock', 'Reserved', 'Available', 'Reorder point', 'Reorder target']);
                foreach (ProductVariant::with('productColor.product')->lazyById(200) as $variant) {
                    $write([$variant->sku, $variant->productColor?->product?->name, $variant->productColor?->name, $variant->size, $variant->is_active ? 'Yes' : 'No', $variant->on_hand, $variant->reserved, Inventory::available($variant), $variant->reorder_point, $variant->reorder_target]);
                }
            }
            fclose($stream);
        }, $dataset.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
