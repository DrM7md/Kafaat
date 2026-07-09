<?php

return [

    /*
    |--------------------------------------------------------------------------
    | إعدادات CORS
    |--------------------------------------------------------------------------
    |
    | ADR-002: الواجهة تُصادَق بجلسة كوكيز httpOnly. هذا يوجب:
    |   - supports_credentials = true
    |   - allowed_origins يسرد النطاق صراحةً (لا '*' — الجمع بين '*' والكوكيز
    |     يرفضه المتصفّح، وهو أيضاً خطأ أمني)
    |   - إدراج 'sanctum/csrf-cookie' في المسارات
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
