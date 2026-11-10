<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\NomorTelepon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FR-PROF-01 #1-#3, #10, UC-06: melihat dan mengubah data profil sendiri.
 *
 * Hanya nama dan nomor telepon yang boleh berubah di sini. Email punya
 * alurnya sendiri (FR-PROF-01 #8, langkah berikutnya) karena harus
 * diverifikasi dulu lewat tautan, dan password punya endpoint sendiri
 * (FR-PROF-01 #9). Menerima field itu di sini akan membuat perubahan email
 * atau password bisa terjadi tanpa verifikasi.
 */
class ProfilController extends Controller
{
    /** Pesan persis dari FR-PROF-01 #10. */
    private const PESAN_NAMA_WAJIB = 'Nama wajib diisi';

    /**
     * FR-PROF-01 #1: tampilkan data profil milik User yang sedang login.
     *
     * Yang dikembalikan hanya empat field yang disebut User Story: nama,
     * email, nomor telepon, dan foto profil. Password, token verifikasi, dan
     * role tidak ikut, karena halaman profil tidak menampilkannya dan token
     * adalah rahasia.
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
        ];
    }
}
