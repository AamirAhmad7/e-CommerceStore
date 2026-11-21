<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use Brick\Math\BigInteger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class Promotions
{
    public static function normalize(?string $code): string
    {
        return strtoupper(trim($code ?? ''));
    }

    public static function summary(Promotion $p): string
    {
        $offer = match ($p->type) {
            'percentage' => rtrim(rtrim(number_format($p->percent_basis_points / 100, 2, '.', ''), '0'), '.').'% off','fixed' => Pricing::format($p->amount_minor).' off',default => 'Free standard shipping'
        };
        $text = $offer.($p->scope === 'storewide' ? ' storewide' : ' '.$p->name.' (selected '.$p->scope.')').'.';
        if ($p->minimum_minor !== null) {
            $text .= ' Eligible merchandise minimum '.Pricing::format($p->minimum_minor).'.';
        }if ($p->minimum_units !== null) {
            $text .= ' At least '.$p->minimum_units.' eligible units.';
        }if ($p->type === 'percentage' && $p->cap_minor !== null) {
            $text .= ' Maximum discount '.Pricing::format($p->cap_minor).'.';
        }

return $text.' PKR checkout only; one offer per order.';
    }

    public function candidates(?string $code = null, bool $lock = false): Collection
    {
        $code = self::normalize($code);

        return Promotion::where(function ($q) use ($code): void {
            $q->where(fn ($a) => $a->where('status', 'active')->where('automatic', true)->where(fn ($d) => $d->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($d) => $d->whereNull('ends_at')->orWhere('ends_at', '>', now())));
            if ($code !== '') {
                $q->orWhere(fn ($c) => $c->where('code', $code)->where('automatic', false));
            }
        })->orderBy('id')->limit(101)->when($lock, fn ($q) => $q->lockForUpdate())->get();
    }

    /** One membership query for the entire basket and every candidate offer. */
    public function memberships(array $lines, Collection $offers): Collection
    {
        $collections = $offers->where('scope', 'collections')->flatMap(fn ($p) => $p->target_ids ?? [])->unique()->values();
        if ($collections->isEmpty()) {
            return collect();
        }

        return DB::table('collection_items')->whereIn('collection_id', $collections)->whereIn('product_id', collect($lines)->map(fn ($l) => $l['product']?->id)->filter()->unique())->get()->groupBy('product_id');
    }

    public function eligible(Promotion $p, array $line, Collection $memberships): bool
    {
        if (! $line['product'] || $line['price'] === null) {
            return false;
        }
        $targets = array_map('intval', $p->target_ids ?? []);

        return match ($p->scope) {
            'storewide' => true,'products' => in_array($line['product']->id, $targets, true),'variants' => in_array($line['id'], $targets, true),'collections' => $memberships->get($line['product']->id, collect())->contains(fn ($m) => in_array((int) $m->collection_id, $targets, true)),default => false
        };
    }

    /** Deterministic largest-remainder allocation; variant ID is the tie-break. */
    public static function allocate(int $discount, array $weights): array
    {
        ksort($weights);
        $total = array_sum($weights);
        $result = [];
        $fractions = [];
        foreach ($weights as $id => $weight) {
            $parts = $total ? BigInteger::of($discount)->multipliedBy($weight)->quotientAndRemainder($total) : [BigInteger::zero(), BigInteger::zero()];
            $result[$id] = $parts[0]->toInt();
            $fractions[$id] = $parts[1]->toInt();
        }
        $left = $discount - array_sum($result);
        uksort($fractions, fn ($a, $b) => ($fractions[$b] <=> $fractions[$a]) ?: ($a <=> $b));
        foreach ($fractions as $id => $unused) {
            if ($left <= 0) {
                break;
            }$result[$id]++;
            $left--;
        }

        return $result;
    }

    public function calculate(Promotion $p, array $quote, array $base, Collection $memberships): array
    {
        $weights = [];
        $units = 0;
        foreach ($quote['lines'] as $line) {
            if ($this->eligible($p, $line, $memberships)) {
                $weights[$line['id']] = $line['total'];
                $units += $line['quantity'];
            }
        }
        $eligible = array_sum($weights);
        $error = null;
        if (! $weights || $eligible <= 0) {
            $error = 'No eligible merchandise in this basket.';
        } elseif ($eligible < ($p->minimum_minor ?? 0) || $units < ($p->minimum_units ?? 0)) {
            $error = 'Eligible basket is below this offer\'s minimum. '.self::summary($p);
        }
        $merch = 0;
        $shipping = 0;
        if (! $error) {
            if ($p->type === 'shipping') {
                $shipping = $base['shipping_minor'];
                if ($shipping === 0) {
                    $error = 'Standard shipping is already free.';
                }
            } else {
                $merch = $p->type === 'fixed' ? min($eligible, $p->amount_minor) : min($eligible, intdiv($eligible * $p->percent_basis_points + 5000, 10000), $p->cap_minor ?? PHP_INT_MAX);
            }
        }
        $allocations = self::allocate($merch, $weights);
        $lines = [];
        foreach ($quote['lines'] as $line) {
            $discount = $allocations[$line['id']] ?? 0;
            $quantity = $line['quantity'];
            $paid = [];
            for ($i = 0; $i < $quantity; $i++) {
                $paid[] = (int) $line['price'] - intdiv($discount, $quantity) - ($i < $discount % $quantity ? 1 : 0);
            } $lines[$line['id']] = ['eligible' => array_key_exists($line['id'], $weights), 'discount' => $discount, 'unit_paid' => $paid];
        }
        $totals = array_replace($base, ['discount_minor' => $merch, 'shipping_minor' => $base['shipping_minor'] - $shipping, 'total_minor' => $base['total_minor'] - $merch - $shipping]);

        return ['totals' => $totals, 'saving' => $merch + $shipping, 'error' => $error, 'lines' => $lines, 'snapshot' => ['id' => $p->id, 'name' => $p->name, 'code' => $p->automatic ? null : $p->code, 'summary' => self::summary($p), 'currency' => 'PKR', 'eligible_minor' => $eligible, 'eligible_units' => $units, 'merchandise_discount_minor' => $merch, 'shipping_discount_minor' => $shipping]];
    }

    public function resolve(array $quote, array $settings, ?string $code = null, bool $lock = false, ?Promotion $preview = null): array
    {
        $base = CommerceSettings::totals($quote['subtotal'], $settings);
        $offers = $preview ? collect([$preview]) : $this->candidates($code, $lock);
        $members = $this->memberships($quote['lines'], $offers);
        $best = ['totals' => $base, 'saving' => 0, 'snapshot' => null, 'lines' => [], 'error' => null];
        $code = self::normalize($code);
        $codeError = $code !== '' ? 'This offer is unavailable.' : null;
        $codeQualified = false;
        foreach ($offers as $p) {
            $error = $preview ? null : $p->availability();
            $result = $this->calculate($p, $quote, $base, $members);
            $error ??= $result['error'];
            if (! $p->automatic && $p->code === $code) {
                $codeError = $error;
                $codeQualified = $error === null;
            }if (! $error && $result['saving'] > $best['saving']) {
                $best = $result;
            }
        }
        $best['code_error'] = $codeError;
        $best['notice'] = $codeError;
        if ($best['snapshot']) {
            $best['notice'] = 'Applied '.$best['snapshot']['name'].': best available saving. Only one offer applies.';
            if ($code !== '' && $best['snapshot']['code'] !== $code) {
                $best['notice'] .= $codeQualified ? ' Your code was kept, but this offer saves at least as much.' : ' '.$codeError;
            }
        }
        if ($preview) {
            $best = $this->calculate($preview, $quote, $base, $members) + ['code_error' => null, 'notice' => null];
        }

        return $best;
    }

    public function publicMessage(?Product $product = null): ?string
    {
        $offers = $this->candidates(session('promotion_code'))->filter(fn ($p) => ! $p->availability());
        if (! $product) {
            $p = $offers->first(fn ($p) => $p->code === session('promotion_code')) ?? $offers->first();

            return $p ? self::summary($p) : null;
        }
        $product->loadMissing('colors.variants');
        $lines = [];
        foreach ($product->colors as $color) {
            foreach ($color->variants as $v) {
                if ($v->is_active) {
                    $lines[] = ['id' => $v->id, 'product' => $product, 'price' => Pricing::resolve($v, 'PKR', $product), 'sku' => $v->sku];
                }
            }
        }
        $members = $this->memberships($lines, $offers);
        foreach ($offers as $offer) {
            $eligible = array_filter($lines, fn ($l) => $this->eligible($offer, $l, $members));
            if ($eligible) {
                return self::summary($offer).($offer->scope === 'variants' ? ' Eligible SKUs: '.implode(', ', array_column($eligible, 'sku')).'.' : '').' Actual savings appear in your cart.';
            }
        }

        return null;
    }

    public static function refundLimit(Order $order): int
    {
        if (! $order->promotion_snapshot || in_array($order->status, ['cancelled', 'returned'], true)) {
            return (int) $order->total_minor;
        }
        $received = DB::table('order_request_lines as l')->join('order_requests as r', 'r.id', '=', 'l.order_request_id')->where('r.order_id', $order->id)->where('r.type', 'return')->where('r.status', 'received')->selectRaw('l.order_line_id, SUM(l.quantity) units')->groupBy('l.order_line_id')->pluck('units', 'order_line_id');
        $net = 0;
        $gross = 0;
        $all = true;
        foreach ($order->lines as $line) {
            $quantity = min((int) ($received[$line->id] ?? 0), (int) $line->quantity);
            $paid = $line->unit_paid_minor ?? array_fill(0, $line->quantity, $line->unit_price_minor);
            $net += array_sum(array_slice($paid, 0, $quantity));
            $gross += $quantity * $line->unit_price_minor;
            $all = $all && $quantity === $line->quantity;
        }
        $tax = $order->subtotal_minor ? BigInteger::of($order->tax_minor)->multipliedBy($gross)->quotient($order->subtotal_minor)->toInt() : 0;

        return min($order->total_minor,$net + $tax + ($all ? $order->shipping_minor : 0));
    }
}
