<?php

return [
    // Always use a durable worker, even when the application's default queue is sync.
    'queue_connection_name' => 'database',
    'queue_name' => 'media',
    'queue_conversions_after_database_commit' => true,
    'image_driver' => 'gd',
    // GD encodes WebP directly; no external optimizer executables are required on Laragon.
    'image_optimizers' => [],
];
