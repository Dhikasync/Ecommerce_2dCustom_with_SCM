<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\Auth\ResetPasswordMail;
use App\Models\Auth\User;
use App\Rules\NomorTelepon;
use Illuminate\Http\JsonResponse;
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
    /** NFR-SEC-02: 5 gagal per akun, 20 per IP, jendela 15 menit. */
    private const BATAS_GAGAL_PER_AKUN = 5;

    private const BATAS_GAGAL_PER_IP = 20;

    private const JENDELA_KUNCI_DETIK = 15 * 60;

    /** Bcrypt cost 12: menyamakan waktu verifikasi saat email tidak ditemukan. */
    private const HASH_PALSU = '$2y$12$Zcb8I3ItxN3/X42kvYN/2uryGwYsvm2zEmfKBecaTDYhAjOlghQ0i';

    /** FR-AUTH-06 #5/#6: 5 permintaan per 15 menit per email + IP. */
    private const BATAS_KIRIM_ULANG = 5;

    /** Satu aturan email untuk semua endpoint, supaya tidak ada yang tertinggal. */
    private const ATURAN_EMAIL = 'required|string|email|max:255';

    private const PESAN_TERLALU_BANYAK = 'Terlalu banyak permintaan, coba lagi beberapa saat lagi';

    private const PESAN_TAUTAN_VERIFIKASI_TIDAK_VALID = 'Tautan verifikasi tidak valid';

    private const PESAN_TAUTAN_RESET_TIDAK_VALID = 'Link reset tidak valid atau sudah dipakai';

    /** FR-AUTH-02 #1: akun lahir "Belum Diverifikasi" dan tidak login otomatis. */
    public function register(Request $request)
    {
        $this->normalisasiEmail($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => self::ATURAN_EMAIL.'|unique:users',
            'no_hp' => ['nullable', new NomorTelepon],
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
     * FR-AUTH-06 #2-#4: signature diperiksa middleware `signed`, lalu user,
     * hash email, dan token. `is_aktif` tidak diperiksa karena verifikasi
     * hanya menandai email; akun Nonaktif tetap ditolak saat login.
     */
    public function verify(Request $request, string $id, string $hash)
    {
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return $this->tautanVerifikasiTidakValid();
        }

        if ($user->hasVerifiedEmail()) {
            return $this->keLogin('Email sudah diverifikasi, silakan login');
        }

        $token = (string) $request->query('token', '');

        if (! $user->email_verification_token || ! hash_equals($user->email_verification_token, hash('sha256', $token))) {
            return $this->tautanVerifikasiTidakValid();
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_token' => null,
        ])->save();

        return $this->keLogin('Email berhasil diverifikasi, silakan login');
    }

    /** FR-AUTH-06 #1/#3. */
    public function notice()
    {
        return response()->json([
            'message' => 'Cek email Anda',
        ]);
    }

    /** FR-AUTH-06 #5/#6: pesan always-generik agar email terdaftar tidak terbuka. */
    public function resend(Request $request)
    {
        $this->normalisasiEmail($request);

        $data = $request->validate([
            'email' => self::ATURAN_EMAIL,
        ]);

        $key = 'kirim-verifikasi:'.$data['email'].'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::BATAS_KIRIM_ULANG)) {
            return $this->terlaluBanyak();
        }

        RateLimiter::hit($key, self::JENDELA_KUNCI_DETIK);

        $user = User::where('email', $data['email'])->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            defer(fn () => $user->sendEmailVerificationNotification());
        }

        return response()->json([
            'message' => 'Jika email terdaftar dan belum diverifikasi, tautan verifikasi sudah dikirim',
        ]);
    }

    /** FR-AUTH-01 #1/#4, FR-AUTH-03 #1. */
    public function showLogin()
    {
        return response()->json([
            'message' => 'Silakan login',
        ]);
    }

    /**
     * FR-AUTH-01 #2/#3/#6, FR-AUTH-03 #1-#4.
     *
     * Kredensial salah, email tidak terdaftar, dan akun nonaktif memakai
     * pesan yang sama supaya email mana yang ada tidak terbocorkan.
     */
    public function login(Request $request)
    {
        $this->normalisasiEmail($request);

        $data = $request->validate([
            'email' => self::ATURAN_EMAIL,
            'password' => 'required|string',
        ], [
            'email.required' => 'Email dan password wajib diisi',
            'password.required' => 'Email dan password wajib diisi',
        ]);

        $kunciAkun = 'login-akun:'.$data['email'];
        $kunciIp = 'login-ip:'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunciAkun, self::BATAS_GAGAL_PER_AKUN)
            || RateLimiter::tooManyAttempts($kunciIp, self::BATAS_GAGAL_PER_IP)) {
            return $this->terlaluBanyak();
        }

        $user = User::where('email', $data['email'])->first();

        // Hash tetap diperiksa saat email tidak ditemukan, supaya waktu respons
        // tidak membocorkan email mana yang terdaftar.
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

        // Hanya hitungan akun yang dibersihkan: hitungan IP dibiarkan agar
        // penyerang tidak mereset jatah IP-nya dengan login ke akunnya sendiri.
        RateLimiter::clear($kunciAkun);

        return response()->json([
            'message' => 'Login berhasil',
            'user' => $user->only(['id', 'name', 'email', 'role']),
            'redirect' => $this->tujuanSetelahLogin($request),
        ]);
    }

    /** FR-AUTH-01 #5, FR-AUTH-03: sesi dibuang dan token CSRF dibuat ulang. */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logout berhasil',
        ]);
    }

    /** FR-AUTH-04 #1/#2: pesan sama untuk email terdaftar maupun tidak. */
    public function forgotPassword(Request $request)
    {
        $this->normalisasiEmail($request);

        $data = $request->validate([
            'email' => self::ATURAN_EMAIL,
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            // Dikirim setelah respons supaya kedua kasus sama lambatnya.
            defer(function () use ($user) {
                $token = Password::createToken($user);

                Mail::to($user->email)->send(new ResetPasswordMail($user, $token));
            });
        }

        return response()->json([
            'message' => 'Jika email terdaftar, link reset telah dikirim',
        ]);
    }

    /** FR-AUTH-04 #3: token baru diperiksa saat password disimpan. */
    public function showReset(Request $request, string $token)
    {
        return response()->json([
            'message' => 'Silakan masukkan password baru',
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    /**
     * FR-AUTH-04 #3-#5: kedaluwarsa diperiksa lebih dulu supaya bisa dibedakan
     * dari token yang tidak dikenal; PasswordBroker menghapus token setelah dipakai.
     */
    public function resetPassword(Request $request)
    {
        $this->normalisasiEmail($request);

        $data = $request->validate([
            'token' => 'required|string',
            'email' => self::ATURAN_EMAIL,
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.required' => 'Password baru wajib diisi',
            'password.min' => 'Password minimal 8 karakter',
            'password.confirmed' => 'Konfirmasi password tidak sesuai',
        ]);

        $tabel = config('auth.passwords.users.table');
        $record = DB::table($tabel)->where('email', $data['email'])->first();

        if (! $record) {
            return $this->tautanResetTidakValid();
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
            return $this->tautanResetTidakValid();
        }

        return $this->keLogin('Password berhasil diubah, silakan login');
    }

    /** Satu bentuk balasan 429 untuk semua pembatas percobaan. */
    private function terlaluBanyak(): JsonResponse
    {
        return response()->json(['message' => self::PESAN_TERLALU_BANYAK], 429);
    }

    /** Hash email salah dan token salah harus dijawab sama persis. */
    private function tautanVerifikasiTidakValid(): JsonResponse
    {
        return response()->json(['message' => self::PESAN_TAUTAN_VERIFIKASI_TIDAK_VALID], 403);
    }

    /** Token tidak dikenal dan token sudah dipakai dijawab sama. */
    private function tautanResetTidakValid(): JsonResponse
    {
        return response()->json(['message' => self::PESAN_TAUTAN_RESET_TIDAK_VALID], 422);
    }

    private function keLogin(string $pesan): JsonResponse
    {
        return response()->json([
            'message' => $pesan,
            'redirect' => url('/login'),
        ]);
    }

    /** Email tidak membedakan huruf besar/kecil, jadi disamakan sebelum validasi. */
    private function normalisasiEmail(Request $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * FR-AUTH-03 #2: `url.intended` datang dari header Host, jadi host yang
     * berbeda dari APP_URL diabaikan supaya login tidak jadi open redirect.
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
