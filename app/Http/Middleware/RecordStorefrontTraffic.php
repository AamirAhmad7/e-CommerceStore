<?php

namespace App\Http\Middleware;

use App\Support\StorefrontAnalytics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordStorefrontTraffic
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('store.analytics_enabled') || ! $request->isMethod('GET') || $response->getStatusCode() !== 200 || $request->user()
            || ! $request->routeIs('home', 'shop', 'products.show', 'collections.show', 'categories.show', 'information.*', 'policies')
            || $request->header('DNT') === '1' || $request->header('Sec-GPC') === '1'
            || preg_match('/bot|crawler|spider|headless|preview|curl|wget/i', $request->userAgent() ?? '')) {
            return;
        }
        try {
            if (StorefrontAnalytics::available()) {
                StorefrontAnalytics::record($request);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
