<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard Cache
    |--------------------------------------------------------------------------
    |
    | These options control the cache keys and TTL used for the admin
    | dashboard aggregate data.
    |
    */

    'admin_kpi_cache_key' => 'dashboard.admin.kpi',

    'admin_completion_rate_cache_key' => 'dashboard.admin.completion_rate',

    'admin_cache_ttl' => env('DASHBOARD_ADMIN_CACHE_TTL', 300),

];
