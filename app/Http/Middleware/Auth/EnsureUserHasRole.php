<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * FR-AUTH-01 #7: menolak akses lintas role di sisi server.
 *
 * Dipakai dengan daftar role yang diizinkan, misalnya
 * `role:admin_ecommerce,admin_scm`. Role lain mendapat 403 dengan pesan
 * yang ditetapkan User Story, bukan halaman 404 atau HTML bawaan.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->hasRole(...$roles)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke halaman ini',
            ], 403);
        }

        return $next($request);
    }
}
