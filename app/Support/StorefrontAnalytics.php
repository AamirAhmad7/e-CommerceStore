<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class StorefrontAnalytics
{
    public static function available(): bool
    {
        return Cache::remember('traffic-schema-'.config('database.default').'-'.config('database.connections.'.config('database.default').'.database'), 60, fn (): bool => Schema::hasTable('storefront_page_stats'));
    }

    /** @return array{device:string,platform:string,browser:string} */
    public static function client(string $agent): array
    {
        $platform = match (true) {
            str_contains($agent, 'Android') => 'Android',
            (bool) preg_match('/iPhone|iPad|iPod/', $agent) => 'iOS / iPadOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Macintosh') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Unknown',
        };
        $browser = match (true) {
            (bool) preg_match('/Edg[A-Za-z]*\//', $agent) => 'Edge',
            str_contains($agent, 'SamsungBrowser') => 'Samsung Internet',
            (bool) preg_match('/OPR\/|Opera/', $agent) => 'Opera',
            (bool) preg_match('/Firefox|FxiOS/', $agent) => 'Firefox',
            (bool) preg_match('/Chrome|CriOS/', $agent) => 'Chrome',
            str_contains($agent, 'Safari') => 'Safari',
            default => 'Other / unknown',
        };
        $device = preg_match('/iPad|Tablet/', $agent) || ($platform === 'Android' && ! str_contains($agent, 'Mobile')) ? 'Tablet' : (preg_match('/Mobile|iPhone|iPod/', $agent) ? 'Phone' : ($platform === 'Unknown' ? 'Unknown' : 'Desktop'));

        return compact('device', 'platform', 'browser');
    }

    public static function record(Request $request): void
    {
        $host = strtolower((string) parse_url($request->header('referer', ''), PHP_URL_HOST));
        $source = $host === '' ? 'Direct / unknown' : ($host === strtolower($request->getHost()) ? 'Internal navigation' : (preg_match('/^[a-z0-9.-]{1,120}$/', $host) ? $host : 'Other referral'));
        $data = ['day' => now()->timezone(config('store.timezone'))->toDateString(), 'page' => '/'.ltrim($request->path(), '/'), 'source' => $source] + self::client(substr($request->userAgent() ?? '', 0, 1000));
        $data['page'] = substr($data['page'], 0, 255);
        $data['bucket'] = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $data['views'] = 1;
        DB::table('storefront_page_stats')->upsert([$data], ['bucket'], ['views' => DB::raw('views + 1')]);
    }

    public static function summary(): array
    {
        return Cache::remember('traffic-dashboard-'.config('database.connections.'.config('database.default').'.database'), 60, function (): array {
            $start = now()->timezone(config('store.timezone'))->startOfDay()->subDays(13);
            $daily = [];
            for ($day = $start->copy(); $day->lte(now()); $day->addDay()) {
                $daily[$day->toDateString()] = ['views' => 0, 'orders' => 0];
            }
            $available = self::available();
            $groups = [];
            if ($available) {
                $query = DB::table('storefront_page_stats')->where('day', '>=', $start->toDateString());
                foreach ((clone $query)->selectRaw('day, SUM(views) AS total')->groupBy('day')->get() as $row) {
                    if (isset($daily[$row->day])) {
                        $daily[$row->day]['views'] = (int) $row->total;
                    }
                }
                foreach (['page', 'source', 'device', 'platform', 'browser'] as $dimension) {
                    $groups[$dimension] = (clone $query)->selectRaw($dimension.', SUM(views) AS total')->groupBy($dimension)->orderByDesc('total')->limit(8)->get()->map(fn ($row): array => ['label' => $row->$dimension, 'count' => (int) $row->total])->all();
                }
            }
            foreach (Order::where('created_at', '>=', $start->copy()->utc())->get(['created_at']) as $order) {
                $day = $order->created_at->timezone(config('store.timezone'))->toDateString();
                if (isset($daily[$day])) {
                    $daily[$day]['orders']++;
                }
            }

            return compact('available', 'daily', 'groups');
        });
    }
}
