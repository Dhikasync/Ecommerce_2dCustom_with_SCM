<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * FR-AUTH-02 #1: membuat akun "Belum Diverifikasi", mengirim email
     * verifikasi, dan TIDAK melakukan login otomatis.
     */
    public function register(Request $request) 
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'no_hp' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'no_hp' => $data['no_hp'] ?? null,
            'password' => $data['password'],
            'role' => 'user',
            'is_aktif' => true,
        ]);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Cek email Anda',
            'email' => $user->email,
        ], 201);
    }

    /**
     * FR-AUTH-06 #2-#4: verifikasi email lewat tautan bertanda tangan.
     *
     * Urutan pemeriksaan: signature (middleware), user, hash email,
     * status aktif, lalu token. Signature sudah diperiksa sebelum method ini.
     */
    public function verify(Request $request, string $id, string $hash)
    {
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json(['message' => 'Tautan verifikasi tidak valid'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email sudah diverifikasi, silakan login',
                'redirect' => url('/login'),
            ]);
        }

        $token = (string) $request->query('token', '');

        if (! $user->email_verification_token || ! hash_equals($user->email_verification_token, $token)) {
            return response()->json(['message' => 'Tautan verifikasi tidak valid'], 403);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_token' => null,
        ])->save();

        return response()->json([
            'message' => 'Email berhasil diverifikasi, silakan login',
            'redirect' => url('/login'),
        ]);
    }

    /**
     * FR-AUTH-06 #1/#3: halaman "Cek email Anda".
     */
    public function notice()
    {
        return response()->json([
            'message' => 'Cek email Anda',
        ]);
    }

    /**
     * FR-AUTH-06 #5/#6: kirim ulang tautan verifikasi.
     *
     * Pesan selalu generik supaya tidak membocorkan email mana yang
     * terdaftar. Batas 5 permintaan per 15 menit per email + IP.
     */
    public function resend(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

        $key = 'kirim-verifikasi:'.Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Terlalu banyak permintaan, coba lagi beberapa saat lagi',
            ], 429);
        }

        RateLimiter::hit($key, 15 * 60);

        $user = User::where('email', $data['email'])->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => 'Jika email terdaftar dan belum diverifikasi, tautan verifikasi sudah dikirim',
        ]);
    }

    /**
     * FR-AUTH-01 #1/#4, FR-AUTH-03 #1: halaman login untuk Admin dan User.
     */
    public function showLogin()
    {
        return response()->json([
            'message' => 'Silakan login',
        ]);
    }

    /**
     * FR-AUTH-01 #2/#3/#6, FR-AUTH-03 #1-#4: login dengan email dan password.
     *
     * Kredensial salah, email tidak terdaftar, dan akun nonaktif dibalas
     * dengan pesan yang sama supaya tidak membocorkan email mana yang ada.
     * Batas 5 kegagalan per 15 menit per email + IP.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string',
        ], [
            'email.required' => 'Email dan password wajib diisi',
            'password.required' => 'Email dan password wajib diisi',
        ]);

        $key = 'login:'.Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Terlalu banyak permintaan, coba lagi beberapa saat lagi',
            ], 429);
        }

        $user = User::where('email', $data['email'])->first();

        $kredensialSalah = ! $user || ! Hash::check($data['password'], $user->password);

        if ($kredensialSalah || ! $user->is_aktif) {
            RateLimiter::hit($key, 15 * 60);

            return response()->json([
                'message' => 'Email atau password salah',
            ], 422);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Silakan verifikasi email Anda terlebih dahulu',
                'can_resend_verification' => true,
            ], 403);
        }

        Auth::login($user);
        $request->session()->regenerate();
        RateLimiter::clear($key);

        return response()->json([
            'message' => 'Login berhasil',
            'user' => $user->only(['id', 'name', 'email', 'role']),
            'redirect' => redirect()->intended(route('home'))->getTargetUrl(),
        ]);
    }
}
