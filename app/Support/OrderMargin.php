<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

final class OrderMargin
{
    /** @return array{cost: ?int, fees: ?int, refunds: int, contribution: ?int, note: string} */
    public static function calculate(Order $order): array
    {
        $order->loadMissing(['lines', 'shipment.settlementLine']);
        $refunds = (int) DB::table('order_refunds')->where('order_id', $order->id)->sum('amount_minor');
        $result = ['cost' => null, 'fees' => null, 'refunds' => $refunds, 'contribution' => null, 'note' => 'Awaiting delivery or completed courier return'];
        if (! in_array($order->status, ['delivered', 'returned'], true)) {
            return $result;
        }
        if ($order->lines->isEmpty() || $order->lines->contains(fn ($line) => $line->unit_cost_minor === null)) {
            $result['note'] = 'Missing cost snapshot; historical costs are not guessed';

            return $result;
        }
        $restocked = DB::table('return_receipts as r')->join('order_request_lines as l', 'l.id', '=', 'r.order_request_line_id')->whereIn('l.order_line_id', $order->lines->modelKeys())->selectRaw('l.order_line_id, SUM(r.resellable) units')->groupBy('l.order_line_id')->pluck('units', 'order_line_id');
        $courierRestocked = DB::table('courier_return_receipts')->whereIn('order_line_id', $order->lines->modelKeys())->pluck('resellable', 'order_line_id');
        $result['cost'] = $order->lines->sum(fn ($line) => max(0, $line->quantity - (int) ($restocked[$line->id] ?? 0) - (int) ($courierRestocked[$line->id] ?? 0)) * (int) $line->unit_cost_minor);
        $settlement = $order->shipment?->settlementLine;
        if (! $settlement) {
            $result['note'] = 'Awaiting recorded courier fees; zero is not assumed';

            return $result;
        }
        $balances = $settlement->balances();
        $result['fees'] = $balances['fee'];
        $result['contribution'] = (int) $order->collected_minor - $refunds - $result['cost'] - $result['fees'];
        $result['note'] = $balances['difference'] || $balances['gross_difference'] ? 'Provisional: courier statement needs reconciliation' : 'Based on recorded collection, costs, refunds and courier fees';
        if ($order->requests()->where('type', 'return')->whereIn('status', ['pending', 'approved'])->exists()) {
            $result['note'] = 'Provisional: return still open';
        }

        return $result;
    }
}
