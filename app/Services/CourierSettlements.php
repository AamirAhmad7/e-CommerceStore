<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\CourierSettlement;
use App\Models\CourierSettlementLine;
use App\Models\Shipment;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CourierSettlements
{
    public const HEADER = ['courier', 'statement_reference', 'tracking_number', 'gross_cod', 'fee_deduction', 'net_remitted'];

    /** @return array<int, array{tracking_number: string, gross_cod: string, fee_deduction: string, net_remitted: string}> */
    public function csv(string $contents, int $courierId, string $reference): array
    {
        Gate::authorize('verify-payments');
        if (strlen($contents) > 1048576) {
            self::invalid('CSV is too large; maximum 1 MB.');
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $contents));
        rewind($stream);
        if (fgetcsv($stream, escape: '') !== self::HEADER) {
            fclose($stream);
            self::invalid('Use the downloadable CSV template and its exact column headers.');
        }
        $name = Courier::findOrFail($courierId)->name;
        $rows = [];
        while (($values = fgetcsv($stream, escape: '')) !== false) {
            if ($values === [null]) {
                continue;
            }
            if (count($values) !== 6 || trim($values[0]) !== $name || trim($values[1]) !== trim($reference)) {
                fclose($stream);
                self::invalid('Every CSV row must have six columns and match the selected courier name and statement reference.');
            }
            $rows[] = ['tracking_number' => trim($values[2]), 'gross_cod' => trim($values[3]), 'fee_deduction' => trim($values[4]), 'net_remitted' => trim($values[5])];
            if (count($rows) > 200) {
                fclose($stream);
                self::invalid('Use at most 200 statement rows per import.');
            }
        }
        fclose($stream);

        return $rows;
    }

    private function normalize(array $data): array
    {
        Validator::make($data, ['courier_id' => 'required|integer|exists:couriers,id', 'external_reference' => 'required|string|max:150', 'payout_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'bank_reference' => 'required|string|max:400', 'review_note' => 'nullable|string|max:1000', 'rows' => 'required|array|min:1|max:200', 'rows.*.tracking_number' => 'required|string|max:150', 'rows.*.gross_cod' => 'required', 'rows.*.fee_deduction' => 'required', 'rows.*.net_remitted' => 'required'])->validate();
        $rows = [];
        foreach ($data['rows'] as $row) {
            $amounts = [];
            foreach (['gross_cod' => 'gross_minor', 'fee_deduction' => 'fee_minor', 'net_remitted' => 'net_minor'] as $input => $field) {
                $value = Pricing::parse((string) $row[$input], 'PKR');
                if ($value === null) {
                    self::invalid('Enter an exact PKR amount, including explicit 0 for no fee.');
                }
                $amounts[$field] = $value;
            }
            $rows[] = ['tracking_number' => trim($row['tracking_number'])] + $amounts;
        }
        usort($rows, fn ($a, $b) => strcmp($a['tracking_number'], $b['tracking_number']));

        return ['courier_id' => (int) $data['courier_id'], 'external_reference' => trim($data['external_reference']), 'payout_date' => $data['payout_date'], 'bank_reference' => trim($data['bank_reference']), 'review_note' => $data['review_note'] ?? '', 'rows' => $rows];
    }

    public function preview(array $data): array
    {
        Gate::authorize('verify-payments');
        $input = $this->normalize($data);
        $plan = $this->plan($input);

        return $plan + ['token' => Crypt::encryptString(json_encode(['input' => $input, 'rows' => $plan['rows']], JSON_THROW_ON_ERROR))];
    }

    private function plan(array $input): array
    {
        $rows = [];
        $seen = [];
        $blocked = false;
        $mismatch = false;
        foreach ($input['rows'] as $row) {
            $tracking = $row['tracking_number'];
            $matches = Shipment::with('order')->where('courier_id', $input['courier_id'])->where('tracking_number', $tracking)->get();
            $problem = null;
            $shipment = $matches->count() === 1 ? $matches->first() : null;
            if (isset($seen[strtolower($tracking)])) {
                $problem = 'Duplicate tracking row';
            } elseif ($matches->count() === 0) {
                $problem = 'Unknown tracking number';
            } elseif ($matches->count() > 1) {
                $problem = 'Ambiguous tracking number';
            } elseif ($shipment->settlementLine()->exists()) {
                $problem = 'Already posted; use an audited adjustment on the original statement';
            } elseif (! $shipment->collection_confirmed_at || $shipment->collected_minor === 0 || $shipment->order->payment_method !== 'cod') {
                $problem = 'Record verified COD collection evidence on the delivered order first';
            }
            $seen[strtolower($tracking)] = true;
            $collection = $shipment?->collected_minor ?? 0;
            $difference = $collection - $row['fee_minor'] - $row['net_minor'];
            $grossDifference = $row['gross_minor'] - $collection;
            $blocked = $blocked || $problem !== null;
            $mismatch = $mismatch || $difference !== 0 || $grossDifference !== 0;
            $rows[] = $row + ['shipment_id' => $shipment?->id, 'order' => $shipment?->order->number, 'collection_minor' => $collection, 'difference' => $difference, 'gross_difference' => $grossDifference, 'problem' => $problem, 'result' => $problem ?? ($difference || $grossDifference ? 'Amount difference - review required' : 'Matched')];
        }

        return ['rows' => $rows, 'blocked' => $blocked, 'mismatch' => $mismatch];
    }

    public function post(array $data, string $token): CourierSettlement
    {
        Gate::authorize('verify-payments');
        $input = $this->normalize($data);
        try {
            $preview = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            self::invalid('Preview this statement before posting.');
        }
        if ($preview['input'] !== $input) {
            self::invalid('The inputs changed. Preview the statement again before posting.');
        }
        $hash = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($input, $preview, $hash, $data): CourierSettlement {
            Courier::lockForUpdate()->findOrFail($input['courier_id']);
            $existing = CourierSettlement::where('courier_id', $input['courier_id'])->where('external_reference', $input['external_reference'])->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    self::invalid('This statement reference is already posted with different entries. Use an audited adjustment.');
                }

                return $existing;
            }
            Shipment::whereIn('id', array_filter(array_column($preview['rows'], 'shipment_id')))->orderBy('id')->lockForUpdate()->get();
            $plan = $this->plan($input);
            if ($plan['rows'] !== $preview['rows']) {
                self::invalid('Collection or settlement records changed. Preview again.');
            }
            if ($plan['blocked']) {
                self::invalid('Correct unknown, duplicate, ambiguous or unconfirmed rows before posting.');
            }
            if ($plan['mismatch'] && (! filter_var($data['accept_discrepancies'] ?? false, FILTER_VALIDATE_BOOLEAN) || trim($input['review_note']) === '')) {
                self::invalid('Explicitly review discrepancies and enter a review note. Differences remain unresolved after posting.');
            }
            $statement = CourierSettlement::create(collect($input)->except('rows')->all() + ['payload_hash' => $hash, 'actor_id' => auth()->id()]);
            foreach ($plan['rows'] as $row) {
                $statement->lines()->create(collect($row)->only(['shipment_id', 'tracking_number', 'collection_minor', 'gross_minor', 'fee_minor', 'net_minor'])->all());
            }

            return $statement;
        }, 5);
    }

    public function adjust(int $lineId, array $data): void
    {
        Gate::authorize('verify-payments');
        Validator::make($data, ['reference' => 'required|string|max:150', 'reason' => 'required|string|max:1000', 'gross_delta' => 'required', 'fee_delta' => 'required', 'net_delta' => 'required'])->validate();
        $values = [];
        foreach (['gross_delta', 'fee_delta', 'net_delta'] as $field) {
            $text = trim((string) $data[$field]);
            if (! preg_match('/^-?\d{1,8}(?:\.\d{1,2})?$/', $text)) {
                self::invalid('Enter signed PKR changes with at most two decimal places.');
            }
            $values[$field] = (str_starts_with($text, '-') ? -1 : 1) * (Pricing::parse(ltrim($text, '-'), 'PKR') ?? 0);
        }
        DB::transaction(function () use ($lineId, $data, $values): void {
            $line = CourierSettlementLine::lockForUpdate()->findOrFail($lineId);
            $existing = DB::table('courier_settlement_adjustments')->where('courier_settlement_line_id', $lineId)->where('reference', trim($data['reference']))->first();
            if ($existing) {
                foreach ($values as $field => $amount) {
                    if ((int) $existing->$field !== $amount) {
                        self::invalid('That adjustment reference already has different amounts.');
                    }
                }

                return;
            }
            $balance = $line->balances();
            foreach (['gross', 'fee', 'net'] as $field) {
                if ($balance[$field] + $values[$field.'_delta'] < 0) {
                    self::invalid('The adjusted gross, fee and net cannot be negative.');
                }
            }
            DB::table('courier_settlement_adjustments')->insert($values + ['courier_settlement_line_id' => $lineId, 'reference' => trim($data['reference']), 'reason' => $data['reason'], 'actor_id' => auth()->id(), 'created_at' => now()]);
        }, 5);
    }

    /** Aggregate ledger rows, including corrections, without loading orders. */
    public static function ledger(): Builder
    {
        $adjustments = DB::table('courier_settlement_adjustments')->selectRaw('courier_settlement_line_id, SUM(gross_delta) gross_delta, SUM(fee_delta) fee_delta, SUM(net_delta) net_delta')->groupBy('courier_settlement_line_id');

        return DB::table('shipments as s')->join('orders as o', 'o.id', '=', 's.order_id')->leftJoin('courier_settlement_lines as l', 'l.shipment_id', '=', 's.id')->leftJoin('courier_settlements as st', 'st.id', '=', 'l.courier_settlement_id')->leftJoinSub($adjustments, 'a', 'a.courier_settlement_line_id', '=', 'l.id')
            ->selectRaw('s.id, s.courier_id, o.created_at as order_date, st.payout_date, l.id as line_id, s.collected_minor, CAST(s.collected_minor AS SIGNED) - CAST(COALESCE(l.fee_minor,0) AS SIGNED) - COALESCE(a.fee_delta,0) - CAST(COALESCE(l.net_minor,0) AS SIGNED) - COALESCE(a.net_delta,0) as difference, CAST(COALESCE(l.gross_minor,0) AS SIGNED) + COALESCE(a.gross_delta,0) - CAST(s.collected_minor AS SIGNED) as gross_difference');
    }

    public static function totals(?int $courier = null, ?string $from = null, ?string $until = null, bool $payoutDates = false, ?string $orderFrom = null, ?string $orderUntil = null): array
    {
        $query = DB::query()->fromSub(self::ledger(), 'ledger')->when($courier, fn ($q) => $q->where('courier_id', $courier));
        $query->when($orderFrom, fn ($q) => $q->whereDate('order_date', '>=', $orderFrom))->when($orderUntil, fn ($q) => $q->whereDate('order_date', '<=', $orderUntil));
        $date = $payoutDates ? 'payout_date' : 'order_date';
        $query->when($from, fn ($q) => $q->whereDate($date, '>=', $from))->when($until, fn ($q) => $q->whereDate($date, '<=', $until));
        $row = $query->selectRaw('COALESCE(SUM(CASE WHEN difference > 0 THEN difference ELSE 0 END),0) outstanding, COALESCE(SUM(CASE WHEN line_id IS NOT NULL THEN CASE WHEN ABS(difference) > ABS(gross_difference) THEN ABS(difference) ELSE ABS(gross_difference) END ELSE 0 END),0) unresolved')->first();

        return ['outstanding' => (int) $row->outstanding, 'unresolved' => (int) $row->unresolved];
    }

    private static function invalid(string $message): never
    {
        throw ValidationException::withMessages(['settlement' => $message]);
    }
}
