<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Services\LocalRecovery;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class StoreBaseline extends Command
{
    protected $signature = 'store:baseline {--path= : One read-only route} {--admin : Authenticate the existing local owner for an admin GET}';

    protected $description = 'Measure local read-only HTML requests with isolated array sessions; does not place orders';

    public function handle(LocalRecovery $recovery): int
    {
        $connection = $recovery->connection();
        if (! $this->option('path')) {
            $paths = ['/', '/collections/demo-launch-jackets', '/products/demo-launch-jacket', '/cart'];
            if ($connection['database'] !== 'leather_store_demo') {
                $this->error('Use --env=demo for representative benchmark routes, or --path for a normal-store read-only check.');

                return self::FAILURE;
            }
            foreach ($paths as $path) {
                for ($sample = 0; $sample < 3; $sample++) {
                    $process = new Process([PHP_BINARY, base_path('artisan'), 'store:baseline', '--env=demo', '--path='.$path, '--no-interaction'], base_path());
                    $process->mustRun();
                    $this->line(trim($process->getOutput()));
                }
            }

            return self::SUCCESS;
        }
        $path = $this->option('path');
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '?')) {
            throw new \InvalidArgumentException('Use a local GET path without query parameters.');
        }
        config(['session.driver' => 'array', 'cache.default' => 'array']);
        $request = Request::create(rtrim(config('app.url'), '/').$path, 'GET');
        app()->instance('request', $request);
        if ($this->option('admin')) {
            abort_unless(str_starts_with($path, '/admin/'), 403);
            auth()->guard('web')->setUser(User::firstOrFail());
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $start = hrtime(true);
        $response = app(Kernel::class)->handle($request);
        $elapsed = (hrtime(true) - $start) / 1000000;
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $body = $response->getContent();
        $this->line(json_encode(['database' => $connection['database'], 'path' => $path, 'status' => $response->getStatusCode(), 'authenticated' => (bool) $this->option('admin'), 'queries' => count($queries), 'sql_ms' => round(array_sum(array_column($queries, 'time')), 2), 'render_ms' => round($elapsed, 2), 'html_bytes' => strlen($body), 'products' => Product::count(), 'visible_products' => Product::visible()->count(), 'empty_state' => str_contains($body, 'No promotions') || str_contains($body, 'No purchase')], JSON_UNESCAPED_SLASHES));

        return $response->getStatusCode() === 200 ? self::SUCCESS : self::FAILURE;
    }
}
