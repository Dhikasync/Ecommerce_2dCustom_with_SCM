<?php

namespace App\Http\Controllers;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
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

    /**
     * FR-AUTH-01 #5, FR-AUTH-03: mengakhiri sesi.
     *
     * Sesi dibuang seluruhnya dan token CSRF dibuat ulang, sehingga ID sesi
     * maupun token lama tidak bisa dipakai lagi.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logout berhasil',
        ]);
    }

    /**
     * FR-AUTH-04 #1/#2: permintaan tautan reset password.
     *
     * Pesan selalu sama, baik email terdaftar maupun tidak, supaya form ini
     * tidak bisa dipakai memeriksa email mana yang punya akun.
     */
    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            $token = Password::broker()->createToken($user);

            Mail::to($user->email)->send(new ResetPasswordMail($user, $token));
        }

        return response()->json([
            'message' => 'Jika email terdaftar, link reset telah dikirim',
        ]);
    }

    /**
     * FR-AUTH-04 #3: halaman pengisian password baru.
     *
     * Endpoint ini hanya mengembalikan data yang dibutuhkan form; token
     * baru diperiksa saat password disimpan.
     */
    public function showReset(Request $request, string $token)
    {
        return response()->json([
            'message' => 'Silakan masukkan password baru',
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    /**
     * FR-AUTH-04 #3-#5: menyimpan password baru dari tautan reset.
     *
     * Token diperiksa lebih dulu supaya kedaluwarsa bisa dibedakan dari
     * token yang tidak dikenal; PasswordBroker yang membandingkan hash
     * token dan menghapusnya setelah dipakai (sekali pakai).
     */
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.required' => 'Password baru wajib diisi',
            'password.min' => 'Password minimal 8 karakter',
            'password.confirmed' => 'Konfirmasi password tidak sesuai',
        ]);

        $tabel = config('auth.passwords.users.table');
        $record = DB::table($tabel)->where('email', $data['email'])->first();

        if (! $record) {
            return response()->json([
                'message' => 'Link reset tidak valid atau sudah dipakai',
            ], 422);
        }

        $kedaluwarsa = Carbon::parse($record->created_at)
            ->addMinutes(config('auth.passwords.users.expire'))
            ->isPast();

        if ($kedaluwarsa) {
            DB::table($tabel)->where('email', $data['email'])->delete();

            return response()->json([
                'message' => 'Link reset sudah kedaluwarsa, silakan minta ulang',
            ], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Link reset tidak valid atau sudah dipakai',
            ], 422);
        }

        return response()->json([
            'message' => 'Password berhasil diubah, silakan login',
            'redirect' => url('/login'),
        ]);
    }
}
