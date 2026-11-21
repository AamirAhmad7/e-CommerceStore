<?php

use App\Services\Checkout;
use App\Services\Fulfillment;
use App\Services\OrderCare;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'leather_store_variant_regression_test') {
    fwrite(STDERR, 'Unsafe database');
    exit(2);
}
Queue::fake();
$payload = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
file_put_contents($argv[2].'.ready-'.$argv[3], 'ready');
$deadline = microtime(true) + 15;
while (! is_file($argv[2])) {
    if (microtime(true) > $deadline) {
        exit(3);
    } usleep(10000);
    clearstatcache();
}
try {
    if (isset($payload['operation'])) {
        $care = app(OrderCare::class);
        match ($payload['operation']) {
            'decide' => $care->decide($payload['order'], $payload['request'], true, $payload['actor'], 'Approved by race worker'),
            'ship' => app(Fulfillment::class)->transition($payload['order'], 'shipped', $payload['actor']),
            'receive' => $care->receive($payload['order'], $payload['request'], $payload['lines'], $payload['actor']),
            'refund' => $care->refund($payload['order'], $payload['amount'], 'PKR', $payload['reference'], $payload['actor']),
        };
        echo 'OK:'.$payload['order'];
    } else {
        $order = app(Checkout::class)->create($payload['guest'], $payload['key'], $payload['customer'], $payload['review']);
        echo 'OK:'.$order->id;
    }
} catch (ValidationException $e) {
    echo 'REJECTED';
}
