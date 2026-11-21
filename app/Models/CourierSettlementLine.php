<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class CourierSettlementLine extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CourierSettlement::class, 'courier_settlement_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /** @return array{gross: int, fee: int, net: int, difference: int, gross_difference: int} */
    public function balances(): array
    {
        $adjustments = DB::table('courier_settlement_adjustments')->where('courier_settlement_line_id', $this->id)->selectRaw('COALESCE(SUM(gross_delta),0) as gross, COALESCE(SUM(fee_delta),0) as fee, COALESCE(SUM(net_delta),0) as net')->first();
        $gross = (int) $this->gross_minor + (int) $adjustments->gross;
        $fee = (int) $this->fee_minor + (int) $adjustments->fee;
        $net = (int) $this->net_minor + (int) $adjustments->net;

        return ['gross' => $gross, 'fee' => $fee, 'net' => $net, 'difference' => (int) $this->collection_minor - $fee - $net, 'gross_difference' => $gross - (int) $this->collection_minor];
    }
}
