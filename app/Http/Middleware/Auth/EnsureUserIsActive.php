<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * FR-AUTH-01: `is_aktif` hanya diperiksa saat login, jadi tanpa middleware ini
 * akun yang dinonaktifkan tetap memakai sesinya sampai kedaluwarsa.
 * Dipasang setelah `auth`, sebelum `role`.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->is_aktif) {
            // Sesi dibuang, bukan hanya request ini yang ditolak.
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message' => 'Akun tidak aktif',
            ], 403);
        }

        return $next($request);
    }
}
