<?php

return [
    'pakistan_only' => (bool) env('STORE_PAKISTAN_ONLY', true),
    'delivery_estimate' => env('STORE_DELIVERY_ESTIMATE', ''),
    'confirm_cod_whatsapp' => (bool) env('STORE_CONFIRM_COD_WHATSAPP', false),
    'marketing_enabled' => (bool) env('STORE_MARKETING_ENABLED', false),
    'ga4_id' => env('STORE_GA4_ID', ''),
    'meta_pixel_id' => env('STORE_META_PIXEL_ID', ''),
    'analytics_enabled' => (bool) env('STORE_ANALYTICS_ENABLED', true),
    'indexing_enabled' => (bool) env('STORE_INDEXING_ENABLED', false),
    'timezone' => env('STORE_TIMEZONE', 'Asia/Karachi'),
    'assisted_sale_hours' => (int) env('ASSISTED_SALE_HOURS', 72),
    'public_cache_store' => env('STORE_PUBLIC_CACHE_STORE', 'file'),
    'brand_name' => 'Sialkot Fashion',
    'media_disk' => env('STORE_MEDIA_DISK', 'public'),
];
