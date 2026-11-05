<?php

$allowedOrigins = array_values(array_filter(array_map(
    static fn (string $origin) => trim($origin),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
)));

$allowedOriginPatterns = array_values(array_filter(array_map(
    static fn (string $pattern) => trim($pattern),
    explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', '')),
)));

if (env('APP_ENV', 'production') === 'local') {
    if ($allowedOrigins === []) {
        $allowedOrigins = [
            'http://localhost:5174',
            'http://127.0.0.1:5174',
        ];
    }

    if ($allowedOriginPatterns === []) {
        $allowedOriginPatterns = [
            '#^https?://([a-z0-9-]+\.)*localhost(:\d+)?$#i',
            '#^https?://([a-z0-9-]+\.)*127\.0\.0\.1(:\d+)?$#i',
            '#^https?://([a-z0-9-]+\.)*[a-z0-9-]+\.test(:\d+)?$#i',
        ];
    }
}

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => $allowedOriginPatterns,

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
