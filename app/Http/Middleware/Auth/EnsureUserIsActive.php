<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * FR-AUTH-01: akun Nonaktif tidak boleh memakai sesi yang sudah terbentuk.
 *
 * Pemeriksaan `is_aktif` di AuthController hanya berjalan saat login. Tanpa
 * middleware ini, akun yang dinonaktifkan lewat `UPDATE users SET is_aktif =
 * false` tetap memegang akses sampai sesinya habis (maksimal 24 jam).
 *
 * Dipasang setelah `auth` dan sebelum `role`, misalnya
 * `['auth', 'active', 'role:admin_ecommerce']`.
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
            // Bukan hanya permintaan ini yang ditolak: sesinya dibuang supaya
            // akun nonaktif tidak bisa melanjutkan dengan cookie yang sama.
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
