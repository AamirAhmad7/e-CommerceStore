<?php

namespace App\Http\Controllers;

use App\Models\AssistedSaleLink;
use App\Services\AssistedSales;
use App\Services\Cart;
use App\Services\CommerceSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AssistedSaleController extends Controller
{
    public function show(Request $request, string $token, Cart $cart): View
    {
        $request->session()->keep('_old_input');
        $link = AssistedSaleLink::with('sale')->where('token_hash', hash('sha256', $token))->first();
        $state = $link?->state() ?? 'unavailable';
        $quote = null;
        $totals = null;
        $problem = null;
        if ($state === 'shared') {
            $quote = $cart->quote($link->items, 'PKR');
            try {
                AssistedSales::requireAvailable($quote);
                $totals = CommerceSettings::totals($quote['subtotal'], CommerceSettings::forQuote($quote, CommerceSettings::get()));
            } catch (ValidationException $exception) {
                $problem = collect($exception->errors())->flatten()->implode(' ');
            }
        }

        return view('shop.assisted-sale', ['link' => $link, 'state' => $state, 'quote' => $quote, 'totals' => $totals, 'problem' => $problem, 'noindex' => true]);
    }

    public function checkout(Request $request, string $token, Cart $cart): View|RedirectResponse
    {
        $link = AssistedSaleLink::with('sale')->where('token_hash', hash('sha256', $token))->first();
        if (! $link || $link->state() !== 'shared') {
            return redirect()->route('assisted.show', $token);
        }
        $request->session()->keep('_old_input');

        return app(CheckoutController::class)->review($request, $cart, $link);
    }
}
