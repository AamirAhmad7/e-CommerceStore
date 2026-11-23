<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SelectDeliveryMarket
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('store.pakistan_only') && ! $request->is('admin', 'admin/*', 'livewire/*')) {
            if ($request->routeIs('inquiry.*')) {
                abort_unless($request->isMethod('GET'), 404);

                return redirect()->route('shop');
            }
            $request->session()->put(['delivery_market' => 'pakistan', 'currency' => 'PKR']);
            if ($request->query->has('currency')) {
                $request->query->set('currency', 'PKR');
            }
            if (! $request->isMethod('GET')) {
                $request->merge(['currency' => 'PKR']);
            }

            return $next($request);
        }
        if ($request->routeIs('home', 'shop', 'categories.show', 'collections.show', 'products.show', 'products.choices')) {
            $requested = $request->query('market');
            if (in_array($requested, ['pakistan', 'international'], true)) {
                $request->session()->put('delivery_market', $requested);
            }
            $market = $request->session()->get('delivery_market');
            if ($market) {
                $currency = $market === 'international' ? 'USD' : 'PKR';
                $request->query->set('currency', $currency);
                $request->session()->put('currency', $currency);
            }
        }
        if ($request->session()->get('delivery_market') === 'international' && $request->routeIs('cart.*', 'checkout.*', 'assisted.show', 'assisted.checkout')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'International selections use an inquiry, not Pakistan checkout.', 'inquiry_url' => route('inquiry.show')], 422);
            }

            return redirect()->route('inquiry.show')->with('status', 'International orders require a quote. Switch to Pakistan on the shop to use Pakistan checkout.');
        }
        if ($request->session()->get('delivery_market') === 'pakistan' && $request->routeIs('cart.*', 'checkout.*')) {
            $request->merge(['currency' => 'PKR']);
            $request->session()->put('currency', 'PKR');
        }
        $response = $next($request);
        if ($request->session()->has('delivery_market') && $request->routeIs('home', 'shop', 'categories.show', 'collections.show', 'products.show', 'products.choices')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
