<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\Purchasing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseDocumentController extends Controller
{
    public function print(PurchaseOrder $purchaseOrder): View
    {
        Purchasing::authorize();

        return view('purchasing.supplier-copy', ['order' => $purchaseOrder->load('lines')]);
    }

    public function export(Request $request): StreamedResponse
    {
        Purchasing::authorize();
        $query = PurchaseOrderLine::with('order')->whereHas('order', fn ($q) => $q->whereIn('status', PurchaseOrder::OPEN)->when($request->integer('supplier'), fn ($q, $id) => $q->where('supplier_id', $id))->when($request->input('scope') === 'overdue', fn ($q) => $q->whereDate('expected_at', '<', today())))->when($request->integer('product'), fn ($q, $id) => $q->whereHas('variant.productColor', fn ($c) => $c->where('product_id', $id)))->whereRaw('quantity > accepted + damaged_short + short_closed')->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Purchase order', 'Supplier', 'Expected date', 'Supplier reference', 'Store SKU', 'Supplier SKU', 'Product', 'Color', 'Size', 'Ordered', 'Accepted', 'Still expected', 'Damaged recorded with replacement requested'], escape: '');
            foreach ($query->lazyById(200) as $line) {
                $row = [$line->order->reference, $line->order->supplier_name, $line->order->expected_at?->format('Y-m-d'), $line->order->supplier_reference, $line->sku, $line->supplier_sku, $line->product_name, $line->color, $line->size, $line->quantity, $line->accepted, $line->outstanding, $line->damaged_replace];
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+@\-\t\r]/', $v) ? "'".$v : $v, $row), escape: '');
            } fclose($out);
        }, 'open-purchase-lines.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
