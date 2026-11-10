<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\NomorTelepon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * FR-PROF-01 #1-#3, #8, #10, UC-06: melihat dan mengubah data profil sendiri.
 *
 * Nama dan nomor telepon berubah langsung (#2, #3, #10). Email punya alur
 * terpisah (#8 + FR-AUTH-06 #7) karena harus dikonfirmasi lewat tautan ke
 * email baru, sementara email lama tetap berlaku sampai konfirmasi selesai.
 * Password juga punya endpoint sendiri (#9).
 *
 * Menerima email atau password di endpoint ubah profil akan membuat
 * perubahan sensitif terjadi tanpa verifikasi, jadi keduanya tidak ada di
 * sana.
 */
class ProfilController extends Controller
{
    /** Pesan persis dari FR-PROF-01 #10. */
    private const PESAN_NAMA_WAJIB = 'Nama wajib diisi';

    /** Pesan yang sama dengan FR-AUTH-02 #4. */
    private const PESAN_EMAIL_TIDAK_VALID = 'Format email tidak valid';

    /** Pesan yang sama dengan FR-AUTH-02 #2. */
    private const PESAN_EMAIL_TERPAKAI = 'Email sudah digunakan';

    /**
     * FR-PROF-01 #1: tampilkan data profil milik User yang sedang login.
     *
     * Yang dikembalikan hanya empat field yang disebut User Story: nama,
     * email, nomor telepon, dan foto profil. Password, token verifikasi, dan
     * role tidak ikut, karena halaman profil tidak menampilkannya dan token
     * adalah rahasia.
     *
     * `pending_email` ditambahkan sebagai kebutuhan tampilan: tanpa itu User
     * tidak tahu ada perubahan email yang masih menunggu konfirmasi dan bisa
     * mengajukannya berulang kali. Dokumen tidak mengaturnya.
     */
    public function tampil(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dataProfil($request->user()),
        ]);
    }

    /**
     * FR-PROF-01 #2, #3, #10: simpan perubahan nama dan nomor telepon.
     */
    public function ubah(Request $request): JsonResponse
    {
        // "Nama wajib diisi" (#10) berarti nama yang hanya berisi spasi juga
        // dianggap kosong. Spasi di ujung dibuang sebelum divalidasi.
        $nama = $request->input('name');

        if (is_string($nama)) {
            $request->merge(['name' => trim($nama)]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Nomor telepon opsional (srs.md:808: "nomor telepon jika diisi
            // User"); kalau diisi, formatnya diperiksa aturan NomorTelepon.
            // `sometimes`: field yang tidak dikirim tidak diubah, supaya form
            // yang hanya mengirim nama tidak menghapus nomor telepon. Kirim
            // null secara eksplisit kalau memang ingin mengosongkannya.
            'no_hp' => ['sometimes', 'nullable', new NomorTelepon],
        ], [
            'name.required' => self::PESAN_NAMA_WAJIB,
            // Dokumen tidak mengatur panjang nama; pesan ini hanya pengaman
            // agar kolom varchar(255) tidak meluap menjadi error 500.
            'name.max' => 'Nama maksimal 255 karakter',
        ]);

        $user = $request->user();

        // Update lewat model, bukan query builder, supaya cast dan mutator
        // (misalnya email huruf kecil) tetap berlaku kalau nanti dipakai.
        $user->update($data);

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'data' => $this->dataProfil($user->refresh()),
        ]);
    }

    /**
     * FR-PROF-01 #9: ubah password dari halaman profil.
     *
     * Password lama wajib dibuktikan lebih dulu: tanpa itu, siapa pun yang
     * memegang sesi User bisa mengunci pemilik akun di luar.
     *
     * Sesi lain ikut terputus tanpa kode tambahan. `AuthenticateSession`
     * menyimpan salinan hash password di setiap sesi, jadi setelah hash di
     * database berubah, sesi lain tidak lagi cocok. Sesi yang sedang dipakai
     * aman karena middleware yang sama menulis ulang salinannya di akhir
     * request.
     */
    public function ubahPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password_lama' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            // Pesan yang belum diatur dokumen, disetujui pemilik proyek.
            'password_lama.required' => 'Password lama wajib diisi',
            // Sama dengan pesan di FR-AUTH-04 #5 dan reset password.
            'password.required' => 'Password baru wajib diisi',
            'password.min' => 'Password minimal 8 karakter',
            // Sama dengan pesan di FR-AUTH-02 #3.
            'password.confirmed' => 'Konfirmasi password tidak sesuai',
        ]);

        $user = $request->user();

        if (! Hash::check($data['password_lama'], $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'password_lama' => 'Password lama tidak sesuai',
            ]);
        }

        // Password dikirim apa adanya: cast `hashed` di model yang meng-hash
        // sekali (NFR-SEC-01). `Hash::make` di sini akan membuat hash kedua.
        // `remember_token` diganti supaya cookie "ingat saya" di perangkat
        // lain tidak bisa dipakai lagi.
        $user->forceFill([
            'password' => $data['password'],
            'remember_token' => Str::random(60),
        ])->save();

        return response()->json([
            'message' => 'Password berhasil diubah',
        ]);
    }

    /**
     * FR-PROF-01 #8: ajukan perubahan email.
     *
     * Email akun BELUM berubah di langkah ini. Yang terjadi: email baru
     * disimpan sebagai `pending_email`, tautan konfirmasi dikirim ke alamat
     * baru, dan pemberitahuan dikirim ke alamat lama (FR-AUTH-06 #7).
     */
    public function ajukanEmail(Request $request): JsonResponse
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        $user = $request->user();

        $data = $request->validate([
            // Email milik sendiri tidak dianggap "sudah dipakai"; kalau tidak
            // di-ignore, kasus "email sama dengan email saat ini" akan
            // dilaporkan sebagai "Email sudah digunakan" dan pesannya salah.
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
        ], [
            'email.required' => 'Email wajib diisi',
            'email.email' => self::PESAN_EMAIL_TIDAK_VALID,
            'email.unique' => self::PESAN_EMAIL_TERPAKAI,
        ]);

        if ($data['email'] === $user->email) {
            // Bukan aturan dokumen, tetapi mengirim tautan konfirmasi ke
            // alamat yang sudah aktif tidak punya arti apa pun.
            throw ValidationException::withMessages([
                'email' => 'Email baru sama dengan email saat ini',
            ]);
        }

        $token = $user->ajukanPerubahanEmail($data['email']);

        // Email dikirim langsung, bukan lewat defer(), karena tidak ada
        // kebutuhan menyamakan waktu respons di sini: pengirimnya User yang
        // sudah login, dan pesan "Email sudah digunakan" memang sudah
        // ditetapkan dokumen (FR-AUTH-02 #2).
        $user->kirimTautanPerubahanEmail($token);

        return response()->json([
            'message' => 'Tautan konfirmasi sudah dikirim ke email baru',
            'pending_email' => $user->pending_email,
        ]);
    }

    /**
     * FR-AUTH-06 #7: konfirmasi lewat tautan yang dikirim ke email baru.
     *
     * Tidak memakai middleware `auth`: tautan biasanya dibuka dari aplikasi
     * email, yang belum tentu berada di browser yang sama dengan sesi User.
     * Keamanan bertumpu pada tanda tangan URL (24 jam) + hash token, bukan
     * pada sesi.
     */
    public function konfirmasiEmail(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::find($id);

        if (! $user || ! $user->pending_email || ! hash_equals(sha1($user->pending_email), $hash)) {
            return response()->json(['message' => 'Tautan konfirmasi tidak valid'], 403);
        }

        $token = (string) $request->query('token', '');

        // Yang tersimpan hash SHA-256 token, dan token baru dibuat setiap
        // permintaan, sehingga tautan lama otomatis tidak berlaku.
        if (! $user->pending_email_token || ! hash_equals($user->pending_email_token, hash('sha256', $token))) {
            return response()->json(['message' => 'Tautan konfirmasi tidak valid'], 403);
        }

        // Alamat baru bisa saja dipakai akun lain setelah permintaan dibuat.
        if (User::where('email', $user->pending_email)->whereKeyNot($user->getKey())->exists()) {
            return response()->json(['message' => self::PESAN_EMAIL_TERPAKAI], 409);
        }

        $user->forceFill([
            'email' => $user->pending_email,
            // Email baru sudah terbukti milik User karena tautannya sampai.
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
            // FR-PROF-01 #1: "foto profil jika ada". Belum ada jalur unggah
            // karena dokumen tidak memuat skenarionya, jadi nilainya null
            // sampai kolom ini diisi dari luar aplikasi.
            'foto_profil' => $user->foto_profil,
            'pending_email' => $user->pending_email,
        ];
    }
}
