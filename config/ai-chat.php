<?php

declare(strict_types=1);

return [
    'enabled' => env('AI_CHAT_ENABLED', false),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],
];
