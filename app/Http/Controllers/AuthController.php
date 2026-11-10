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
     * NFR-SEC-02: setelah 5 kali gagal beruntun, akun dikunci sementara
     * 15 menit. Batas per IP lebih longgar supaya satu kantor/NAT tidak
     * saling mengunci, tetapi tetap mencegah satu IP menebak banyak akun.
     */
    private const BATAS_GAGAL_PER_AKUN = 5;

    private const BATAS_GAGAL_PER_IP = 20;

    private const JENDELA_KUNCI_DETIK = 15 * 60;

    /**
     * Hash bcrypt dari string acak, hanya untuk menyamakan biaya verifikasi
     * saat email tidak ditemukan. Dibuat dengan cost 12, sama dengan
     * BCRYPT_ROUNDS di .env.example. Kalau cost diubah, buat ulang hash ini.
     */
    private const HASH_PALSU = '$2y$12$Zcb8I3ItxN3/X42kvYN/2uryGwYsvm2zEmfKBecaTDYhAjOlghQ0i';

    /**
     * FR-AUTH-02 #1: membuat akun "Belum Diverifikasi", mengirim email
     * verifikasi, dan TIDAK melakukan login otomatis.
     */
    public function register(Request $request)
    {
        $this->normalisasiEmail($request);

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
     * Urutan pemeriksaan: signature (middleware `signed`), user, hash email,
     * lalu token. Signature sudah diperiksa sebelum method ini dijalankan.
     *
     * Status akun (is_aktif) sengaja tidak diperiksa di sini: verifikasi hanya
     * menandai email, dan akun Nonaktif tetap ditolak saat login.
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

        // Yang tersimpan adalah hash SHA-256 token, bukan token aslinya.
        if (! $user->email_verification_token || ! hash_equals($user->email_verification_token, hash('sha256', $token))) {
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
        $this->normalisasiEmail($request);

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
            // Sama seperti lupa password: pengiriman email tidak boleh
            // membedakan waktu respons email terdaftar dan tak terdaftar.
            defer(fn () => $user->sendEmailVerificationNotification());
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
     * Batas 5 kegagalan per akun dan 20 kegagalan per IP dalam 15 menit.
     */
    public function login(Request $request)
    {
        $this->normalisasiEmail($request);

        $data = $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string',
        ], [
            'email.required' => 'Email dan password wajib diisi',
            'password.required' => 'Email dan password wajib diisi',
        ]);

        $kunciAkun = 'login-akun:'.Str::lower($data['email']);
        $kunciIp = 'login-ip:'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunciAkun, self::BATAS_GAGAL_PER_AKUN)
            || RateLimiter::tooManyAttempts($kunciIp, self::BATAS_GAGAL_PER_IP)) {
            return response()->json([
                'message' => 'Terlalu banyak permintaan, coba lagi beberapa saat lagi',
            ], 429);
        }

        $user = User::where('email', $data['email'])->first();

        // Hash selalu diperiksa, termasuk saat email tidak ditemukan. Tanpa
        // ini, respons untuk email tak terdaftar kembali secepat kueri
        // database sementara email terdaftar membayar bcrypt (~225 ms),
        // sehingga form login bisa dipakai memetakan email admin.
        $hash = $user?->password ?? self::HASH_PALSU;
        $cocok = Hash::check($data['password'], $hash);

        $kredensialSalah = ! $user || ! $cocok;

        if ($kredensialSalah || ! $user->is_aktif) {
            RateLimiter::hit($kunciAkun, self::JENDELA_KUNCI_DETIK);
            RateLimiter::hit($kunciIp, self::JENDELA_KUNCI_DETIK);

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

        // Hanya hitungan akun yang dibersihkan. Hitungan IP sengaja dibiarkan
        // agar penyerang tidak bisa mereset jatah IP-nya dengan login ke akun
        // miliknya sendiri.
        RateLimiter::clear($kunciAkun);

        return response()->json([
            'message' => 'Login berhasil',
            'user' => $user->only(['id', 'name', 'email', 'role']),
            'redirect' => $this->tujuanSetelahLogin($request),
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
        $this->normalisasiEmail($request);

        $data = $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            // Token dibuat dan email dikirim setelah respons terkirim, supaya
            // email terdaftar tidak lebih lambat dari email tak terdaftar.
            defer(function () use ($user) {
                $token = Password::createToken($user);

                Mail::to($user->email)->send(new ResetPasswordMail($user, $token));
            });
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
        $this->normalisasiEmail($request);

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

    /**
     * Email tidak membedakan huruf besar/kecil, jadi input disamakan lebih
     * dulu sebelum validasi. Tanpa ini, aturan `unique` dan pencarian
     * `where('email', ...)` memakai bentuk berbeda dari yang disimpan model.
     */
    private function normalisasiEmail(Request $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * FR-AUTH-03 #2: kembali ke halaman yang diminta sebelum login.
     *
     * Nilai `url.intended` dibentuk dari URL request, termasuk header Host.
     * Kalau host tujuannya berbeda dari APP_URL, permintaan diabaikan dan
     * User diarahkan ke halaman utama, supaya login tidak bisa dipakai
     * sebagai open redirect ke situs lain.
     */
    private function tujuanSetelahLogin(Request $request): string
    {
        $tujuan = (string) $request->session()->pull('url.intended', route('home'));

        $hostTujuan = parse_url($tujuan, PHP_URL_HOST);
        $hostAplikasi = parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($hostTujuan !== null && $hostAplikasi !== null && $hostTujuan !== $hostAplikasi) {
            return route('home');
        }

        return $tujuan;
    }
}
