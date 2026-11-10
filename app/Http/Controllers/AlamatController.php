<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\User;
use App\Rules\NomorTelepon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FR-PROF-01 #4-#7, UC-07: kelola alamat pengiriman milik User.
 *
 * Lokasi tidak pernah diketik bebas: `kode_lokasi` dan `label_lokasi` wajib
 * diisi dari hasil pencarian (GET /lokasi). Kode lokasi inilah yang nanti
 * dipakai menghitung ongkir, jadi alamat tanpa lokasi tidak boleh tersimpan
 * (#4, #5, srs.md:174).
 *
 * Penanda utama hanya bisa diubah lewat aksi tersendiri (#7), tidak lewat
 * ubah alamat. Kalau ikut di ubah alamat, dua alamat bisa sama-sama utama dan
 * unique index `addresses_utama_unique` di database akan menolak.
 */
class AlamatController extends Controller
{
    private const PESAN_TIDAK_DITEMUKAN = 'Alamat tidak ditemukan';

    /**
     * FR-PROF-01 #1, srs.md:375: daftar alamat milik User, utama lebih dulu.
     */
    public function daftar(Request $request): JsonResponse
    {
        $alamat = $request->user()
            ->addresses()
            ->orderByDesc('is_utama')
            ->orderBy('id')
            ->get()
            ->map(fn (Address $item) => $item->ringkas())
            ->all();

        return response()->json(['data' => $alamat]);
    }

    /**
     * FR-PROF-01 #4: tambah alamat baru.
     *
     * Alamat pertama otomatis menjadi utama supaya checkout selalu punya
     * pilihan default. Ini keputusan di luar dokumen, dicatat di README.
     */
    public function simpan(Request $request): JsonResponse
    {
        $data = $this->validasi($request);
        $user = $request->user();

        $alamat = DB::transaction(function () use ($user, $data) {
            $pertama = ! $user->addresses()->exists();

            return $user->addresses()->create($data + ['is_utama' => $pertama]);
        });

        return response()->json([
            'message' => 'Alamat berhasil ditambahkan',
            'data' => $alamat->ringkas(),
        ], 201);
    }

    /**
     * FR-PROF-01 #5: ubah alamat tersimpan.
     *
     * Lokasi tetap wajib, termasuk untuk alamat lama yang belum punya lokasi —
     * skenario #5 menyebut alamat seperti itu hanya dapat disimpan setelah
     * User memilih lokasi.
     */
    public function ubah(Request $request, int $id): JsonResponse
    {
        $alamat = $this->milikUser($request->user(), $id);

        if (! $alamat) {
            return $this->tidakDitemukan();
        }

        // `is_utama` tidak ada di $data, jadi tidak bisa ikut berubah.
        $alamat->update($this->validasi($request));

        return response()->json([
            'message' => 'Alamat berhasil diperbarui',
            'data' => $alamat->refresh()->ringkas(),
        ]);
    }

    /**
     * FR-PROF-01 #6: hapus alamat, hanya kalau User punya lebih dari satu.
     *
     * Kalau yang dihapus adalah alamat utama, alamat lain yang paling lama
     * dipromosikan supaya User tidak pernah kehilangan pilihan default.
     */
    public function hapus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $alamat = $this->milikUser($user, $id);

        if (! $alamat) {
            return $this->tidakDitemukan();
        }

        $jumlah = $user->addresses()->count();

        if ($jumlah <= 1) {
            return response()->json([
                'message' => 'Minimal satu alamat harus tetap tersimpan',
            ], 422);
        }

        DB::transaction(function () use ($user, $alamat) {
            $adalahUtama = $alamat->is_utama;
            $alamat->delete();

            if ($adalahUtama) {
                $user->addresses()->orderBy('id')->first()?->update(['is_utama' => true]);
            }
        });

        return response()->json(['message' => 'Alamat berhasil dihapus']);
    }

    /**
     * FR-PROF-01 #7: tandai satu alamat sebagai utama.
     *
     * Urutannya penting: alamat lain dilepas dulu, baru target ditandai,
     * karena PostgreSQL memeriksa unique index segera (bukan di akhir
     * transaksi).
     */
    public function jadikanUtama(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $alamat = $this->milikUser($user, $id);

        if (! $alamat) {
            return $this->tidakDitemukan();
        }

        DB::transaction(function () use ($user, $alamat) {
            $user->addresses()->whereKeyNot($alamat->getKey())->update(['is_utama' => false]);
            $alamat->update(['is_utama' => true]);
        });

        return response()->json([
            'message' => 'Alamat utama berhasil ditetapkan',
            'data' => $alamat->refresh()->ringkas(),
        ]);
    }

    /**
     * Pencarian dibatasi ke alamat milik User sendiri (NFR-SEC-03): alamat
     * orang lain dijawab 404, bukan 403, supaya keberadaannya tidak terbuka.
     */
    private function milikUser(User $user, int $id): ?Address
    {
        return $user->addresses()->whereKey($id)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request): array
    {
        // Nama dan lokasi yang hanya berisi spasi dianggap kosong.
        foreach (['nama_penerima', 'alamat_lengkap', 'kode_lokasi', 'label_lokasi'] as $field) {
            $nilai = $request->input($field);

            if (is_string($nilai)) {
                $request->merge([$field => trim($nilai)]);
            }
        }

        return $request->validate([
            'nama_penerima' => ['required', 'string', 'max:255'],
            // Nomor penerima bisa berbeda dari nomor akun, jadi tetap diminta.
            'no_hp' => ['required', 'string', new NomorTelepon],
            'alamat_lengkap' => ['required', 'string'],
            'kode_lokasi' => ['required', 'string', 'max:255'],
            'label_lokasi' => ['required', 'string', 'max:255'],
        ], [
            // Pesan turunan pola "Nama wajib diisi" (#10); dokumen hanya
            // menetapkan pesan format nomor telepon (#3).
            'nama_penerima.required' => 'Nama penerima wajib diisi',
            'no_hp.required' => 'Nomor telepon wajib diisi',
            'alamat_lengkap.required' => 'Alamat lengkap wajib diisi',
            'kode_lokasi.required' => 'Lokasi wajib dipilih',
            'label_lokasi.required' => 'Lokasi wajib dipilih',
        ]);
    }

    private function tidakDitemukan(): JsonResponse
    {
        return response()->json(['message' => self::PESAN_TIDAK_DITEMUKAN], 404);
    }
}
