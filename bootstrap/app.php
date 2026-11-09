<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Modul SRS dipisah per file. Middleware `web` ditulis eksplisit di
        // sini karena group itu hanya otomatis untuk routes/web.php.
        then: function () {
            Route::middleware('web')->group(base_path('routes/auth.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // FR-AUTH-01 #7: `role:admin_ecommerce` dan sejenisnya.
            'role' => EnsureUserHasRole::class,
            // FR-AUTH-01: `active` menolak sesi milik akun yang dinonaktifkan.
            'active' => EnsureUserIsActive::class,
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
