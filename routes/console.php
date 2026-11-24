<?php

use App\Jobs\OperationsHeartbeat;
use App\Support\OperationsHealth;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Schedule::command('orders:expire')->everyMinute()->withoutOverlapping();
Artisan::command('analytics:prune', function (): void {
    if (Schema::hasTable('storefront_page_stats')) {
        $count = DB::table('storefront_page_stats')->where('day', '<', now()->subDays(90)->toDateString())->delete();
        $this->info('Removed '.$count.' expired aggregate rows.');
    }
})->purpose('Keep the most recent 90 days of anonymous storefront aggregates');
Schedule::command('analytics:prune')->daily()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    Cache::store('file')->put(OperationsHealth::key('scheduler'), now()->timestamp, now()->addDay());
    OperationsHeartbeat::dispatch()->onConnection('database')->onQueue('default');
})->name('operations-heartbeat')->everyFiveMinutes()->withoutOverlapping();

if (config('store.local_backup_enabled')) {
    Schedule::command('store:backup')->dailyAt('02:00')->timezone(config('store.timezone'))->withoutOverlapping();
}
