<?php

return [
    'indexing_enabled' => (bool) env('STORE_INDEXING_ENABLED', false),
    'timezone' => env('STORE_TIMEZONE', 'Asia/Karachi'),
    'assisted_sale_hours' => (int) env('ASSISTED_SALE_HOURS', 72),
    'public_cache_store' => env('STORE_PUBLIC_CACHE_STORE', 'file'),
    'brand_name' => 'Sialkot Fashion',
    'media_disk' => env('STORE_MEDIA_DISK', 'public'),
];
