<?php

declare(strict_types=1);

return [
    'enabled' => env('AI_CHAT_ENABLED', false),

    'daily_limit' => (int) env('AI_CHAT_DAILY_LIMIT', 50),

    'auto_title' => [
        'enabled' => env('AI_CHAT_AUTO_TITLE_ENABLED', true),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],
];
