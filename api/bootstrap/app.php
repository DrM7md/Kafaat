<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ADR-002: تُصادَق الواجهة بجلسة كوكيز httpOnly لا بتوكن في localStorage.
        // statefulApi() يُفعّل EnsureFrontendRequestsAreStateful لنطاقات SANCTUM_STATEFUL_DOMAINS.
        // مسار التوكن يبقى متاحاً لتطبيق الجوال مستقبلاً دون تغيير.
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
