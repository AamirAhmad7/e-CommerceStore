<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use App\Services\Cart;
use App\Services\CommerceSettings;
use App\Services\Pricing;
use App\Services\Promotions;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PromotionController extends Controller
{
    public function apply(Request $request, Cart $cart): RedirectResponse
    {
        $data = $request->validate(['code' => 'required|string|max:40']);
        $code = Promotions::normalize($data['code']);
        $offer = Promotion::where('code', $code)->where('automatic', false)->first();
        $error = $offer?->availability() ?? ($offer ? null : 'This offer is unavailable.');
        if ($error) {
            throw ValidationException::withMessages(['code' => $error.' Your previous code has been kept.']);
        }$quote = $cart->quote($cart->items($request));
        $settings = CommerceSettings::get();
        $settings['shipping_minor'] ??= 0;
        $settings['tax_basis_points'] ??= 0;
        $result = app(Promotions::class)->resolve($quote, $settings, $code);
        if ($result['code_error'] && ! ($offer->type === 'shipping' && $result['code_error'] === 'Standard shipping is already free.')) {
            throw ValidationException::withMessages(['code' => $result['code_error'].' Your previous code has been kept.']);
        }$request->session()->put('promotion_code', $code);

        return redirect()->route('cart.show')->with('status', $result['notice'] ?? 'Code saved. Final saving is checked at PKR checkout.');
    }

    public function remove(Request $request): RedirectResponse
    {
        $request->session()->forget('promotion_code');

        return redirect()->route('cart.show')->with('status', 'Code removed. The best qualifying automatic offer still applies.');
    }

    public static function results(Promotion $promotion, ?string $from = null, ?string $to = null): Builder
    {
        return DB::table('promotion_redemptions as r')->join('orders as o', 'o.id', '=', 'r.order_id')->where('r.promotion_id', $promotion->id)->when($from, fn ($q) => $q->where('o.created_at', '>=', Carbon::parse($from, config('store.timezone'))->startOfDay()->utc()))->when($to, fn ($q) => $q->where('o.created_at', '<', Carbon::parse($to, config('store.timezone'))->startOfDay()->addDay()->utc()));
    }

    public function export(Request $request, Promotion $promotion): StreamedResponse
    {
        Gate::authorize('manage-commerce');
        $data = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d'.($request->filled('from') ? '|after_or_equal:from' : '')]);
        $query = self::results($promotion, $data['from'] ?? null, $data['to'] ?? null)->select('r.id', 'o.number', 'o.created_at', 'o.status', 'o.currency', 'o.promotion_snapshot', 'r.eligible_minor', 'r.merchandise_discount_minor', 'r.shipping_discount_minor')->orderBy('r.id');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order', 'Date ('.config('store.timezone').')', 'Campaign', 'Code (text)', 'Status', 'Currency', 'Eligible merchandise', 'Merchandise discount', 'Shipping discount'], escape: '');
            foreach ($query->lazy(200) as $row) {
                $snap = json_decode($row->promotion_snapshot, true);
                $values = [$row->number, Carbon::parse($row->created_at, 'UTC')->timezone(config('store.timezone'))->format('Y-m-d H:i:s'), $snap['name'], $snap['code'] === null ? '' : "'".$snap['code'], $row->status, $row->currency, Pricing::decimal($row->eligible_minor), Pricing::decimal($row->merchandise_discount_minor), Pricing::decimal($row->shipping_discount_minor)];
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+@\-\t\r]/', $v) ? "'".$v : $v, $values), escape: '');
            }fclose($out);
        }, 'promotion-orders.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
