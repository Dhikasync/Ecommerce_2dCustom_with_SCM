<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // FR-AUTH-01 #7: `role:admin_ecommerce` dan sejenisnya.
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tautan bertanda tangan ditolak sebelum controller berjalan. Tanpa
        // handler ini Laravel membalas halaman HTML 403, bukan JSON.
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            $expires = (int) $request->query('expires', 0);

            if ($expires > 0 && $expires < now()->getTimestamp()) {
                return response()->json([
                    'message' => 'Tautan verifikasi sudah kedaluwarsa',
                    'can_resend' => true,
                ], 403);
            }

            return response()->json(['message' => 'Tautan verifikasi tidak valid'], 403);
        });
    })->create();
