<?php

namespace App\Http\Controllers\Profil;

use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use App\Rules\NomorTelepon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * FR-PROF-01 #1-#3, #8-#10, UC-06: melihat dan mengubah data profil sendiri.
 *
 * Email dan password punya endpoint sendiri: menerimanya di endpoint ubah
 * profil akan membuat perubahan sensitif terjadi tanpa verifikasi.
 */
class ProfilController extends Controller
{
    private const PESAN_NAMA_WAJIB = 'Nama wajib diisi';

    private const PESAN_EMAIL_TIDAK_VALID = 'Format email tidak valid';

    private const PESAN_EMAIL_TERPAKAI = 'Email sudah digunakan';

    /**
     * Hanya field yang disebut User Story #1. `pending_email` ditambahkan di
     * luar dokumen supaya User tahu ada perubahan email yang belum dikonfirmasi.
     */
    public function tampil(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dataProfil($request->user()),
        ]);
    }

    /** FR-PROF-01 #2, #3, #10. */
    public function ubah(Request $request): JsonResponse
    {
        $nama = $request->input('name');

        if (is_string($nama)) {
            $request->merge(['name' => trim($nama)]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // `sometimes`: form yang hanya mengirim nama tidak menghapus nomor
            // telepon. Kirim null eksplisit untuk mengosongkannya (srs.md:808).
            'no_hp' => ['sometimes', 'nullable', new NomorTelepon],
        ], [
            'name.required' => self::PESAN_NAMA_WAJIB,
            'name.max' => 'Nama maksimal 255 karakter',
        ]);

        $user = $request->user();

        // Lewat model, bukan query builder, supaya cast dan mutator tetap berlaku.
        $user->update($data);

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'data' => $this->dataProfil($user->refresh()),
        ]);
    }

    /**
     * FR-PROF-01 #9.
     *
     * Sesi lain ikut terputus karena `AuthenticateSession` menyimpan salinan
     * hash password per sesi; setelah hash di database berubah, salinan lama
     * tidak cocok lagi.
     */
    public function ubahPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password_lama' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password_lama.required' => 'Password lama wajib diisi',
            'password.required' => 'Password baru wajib diisi',
            'password.min' => 'Password minimal 8 karakter',
            'password.confirmed' => 'Konfirmasi password tidak sesuai',
        ]);

        $user = $request->user();

        if (! Hash::check($data['password_lama'], $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'password_lama' => 'Password lama tidak sesuai',
            ]);
        }

        // Password dikirim apa adanya: cast `hashed` yang meng-hash sekali
        // (NFR-SEC-01). `remember_token` diganti agar cookie "ingat saya" di
        // perangkat lain tidak berlaku lagi.
        $user->forceFill([
            'password' => $data['password'],
            'remember_token' => Str::random(60),
        ])->save();

        return response()->json([
            'message' => 'Password berhasil diubah',
        ]);
    }

    /**
     * FR-PROF-01 #8 + FR-AUTH-06 #7: email akun belum berubah di sini. Email
     * baru disimpan sebagai `pending_email` dan tautan dikirim ke alamat itu.
     */
    public function ajukanEmail(Request $request): JsonResponse
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        $user = $request->user();

        $data = $request->validate([
            // Email milik sendiri tidak dianggap "sudah dipakai"; tanpa ignore,
            // kasus "email sama dengan email saat ini" mendapat pesan yang salah.
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
        ], [
            'email.required' => 'Email wajib diisi',
            'email.email' => self::PESAN_EMAIL_TIDAK_VALID,
            'email.unique' => self::PESAN_EMAIL_TERPAKAI,
        ]);

        if ($data['email'] === $user->email) {
            throw ValidationException::withMessages([
                'email' => 'Email baru sama dengan email saat ini',
            ]);
        }

        $token = $user->ajukanPerubahanEmail($data['email']);

        // Dikirim langsung, bukan defer(): yang meminta User yang sudah login,
        // dan pesan "Email sudah digunakan" memang sudah terbuka (FR-AUTH-02 #2).
        $user->kirimTautanPerubahanEmail($token);

        return response()->json([
            'message' => 'Tautan konfirmasi sudah dikirim ke email baru',
            'pending_email' => $user->pending_email,
        ]);
    }

    /**
     * FR-AUTH-06 #7. Tanpa middleware `auth`: tautan dibuka dari aplikasi
     * email, belum tentu di browser yang sama. Keamanan bertumpu pada tanda
     * tangan URL (24 jam) + hash token.
     */
    public function konfirmasiEmail(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::find($id);

        if (! $user || ! $user->pending_email || ! hash_equals(sha1($user->pending_email), $hash)) {
            return response()->json(['message' => 'Tautan konfirmasi tidak valid'], 403);
        }

        $token = (string) $request->query('token', '');

        if (! $user->pending_email_token || ! hash_equals($user->pending_email_token, hash('sha256', $token))) {
            return response()->json(['message' => 'Tautan konfirmasi tidak valid'], 403);
        }

        // Alamat baru bisa saja dipakai akun lain setelah permintaan dibuat.
        if (User::where('email', $user->pending_email)->whereKeyNot($user->getKey())->exists()) {
            return response()->json(['message' => self::PESAN_EMAIL_TERPAKAI], 409);
        }

        $user->forceFill([
            'email' => $user->pending_email,
            // Email baru terbukti milik User karena tautannya sampai.
            'email_verified_at' => now(),
            'pending_email' => null,
            'pending_email_token' => null,
            'email_verification_token' => null,
        ])->save();

        return response()->json([
            'message' => 'Email berhasil diubah',
            'email' => $user->email,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dataProfil(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'no_hp' => $user->no_hp,
            // FR-PROF-01 #1 "foto profil jika ada": belum ada jalur unggah
            // karena dokumen tidak memuat skenarionya.
            'foto_profil' => $user->foto_profil,
            'pending_email' => $user->pending_email,
            // srs.md:375: profil juga menampilkan daftar alamat, utama lebih dulu.
            'alamat' => $user->addresses()
                ->orderByDesc('is_utama')
                ->orderBy('id')
                ->get()
                ->map(fn ($item) => $item->ringkas())
                ->all(),
        ];
    }
}
