<?php

namespace App\Jobs;

use App\Support\OperationsHealth;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class OperationsHeartbeat implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 86400;

    public function uniqueId(): string
    {
        return OperationsHealth::key('heartbeat-job');
    }

    public function uniqueVia(): Repository
    {
        return Cache::store('file');
    }

    public function handle(): void
    {
        Cache::store('file')->put(OperationsHealth::key('worker'), now()->timestamp, now()->addDay());
    }
}
