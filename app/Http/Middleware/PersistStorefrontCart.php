<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PersistStorefrontCart
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin', 'admin/*', 'livewire/*')) {
            return $next($request);
        }
        if (! $request->session()->has('cart')) {
            $saved = json_decode($request->cookie('store_cart', '{}'), true);
            $items = [];
            if (is_array($saved) && count($saved) <= 50) {
                foreach ($saved as $id => $quantity) {
                    if (ctype_digit((string) $id) && (int) $id > 0 && is_int($quantity) && $quantity > 0 && $quantity <= 100) {
                        $items[(int) $id] = $quantity;
                    }
                }
            }
            $request->session()->put('cart', $items);
        }
        $response = $next($request);
        $response->headers->setCookie(cookie('store_cart', json_encode($request->session()->get('cart', []), JSON_THROW_ON_ERROR), 43200, '/', null, $request->isSecure(), true, false, 'lax'));

        return $response;
    }
}
