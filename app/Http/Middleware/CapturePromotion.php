<?php

namespace App\Http\Middleware;

use App\Models\Promotion;
use App\Services\Promotions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CapturePromotion
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('shop', 'collections.show', 'products.show', 'categories.show', 'home')) {
            return $next($request);
        }
        if ($request->has('offer')) {
            $code = $request->query('offer');
            $p = is_string($code) && strlen($code) <= 80 ? Promotion::where('code', Promotions::normalize($code))->where('automatic', false)->first() : null;
            if ($p && ! $p->availability()) {
                $request->session()->put('promotion_code', $p->code);
            } else {
                $request->session()->flash('status', $p?->availability() ?? 'This offer is unavailable. Your existing code and cart have been kept.');
            }
        }
        $response = $next($request);
        if ($request->session()->has('promotion_code')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

return $response;
    }
}
