<?php

namespace App\Http\Controllers\Profil;

use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use App\Models\Profil\Address;
use App\Rules\NomorTelepon;
use App\Services\Profil\LokasiDitawarkan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FR-PROF-01 #4-#7, UC-07. `kode_lokasi` hanya dari GET /lokasi dan
 * `label_lokasi` versi server (srs.md:174); `is_utama` hanya lewat aksi #7.
 */
class AlamatController extends Controller
{
    private const PESAN_TIDAK_DITEMUKAN = 'Alamat tidak ditemukan';

    /** Pesan persis FR-PROF-01 #11, dipakai saat kode lokasi tidak dikenal. */
    private const PESAN_LOKASI_TIDAK_DIKENAL = 'Lokasi tidak ditemukan, coba kata kunci lain';

    /** FR-PROF-01 #1, srs.md:375: utama lebih dulu. */
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
     * FR-PROF-01 #4. Alamat pertama otomatis utama (keputusan luar dokumen,
     * dokumen (README) supaya checkout punya default.
     */
    public function simpan(Request $request, LokasiDitawarkan $ditawarkan): JsonResponse
    {
        $data = $this->validasi($request, $ditawarkan);
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
     * FR-PROF-01 #5. Lokasi tetap wajib, termasuk untuk alamat lama yang belum
     * punya lokasi: alamat seperti itu baru bisa disimpan setelah User memilih.
     */
    public function ubah(Request $request, int $id, LokasiDitawarkan $ditawarkan): JsonResponse
    {
        $alamat = $this->milikUser($request->user(), $id);

        if (! $alamat) {
            return $this->tidakDitemukan();
        }

        // `is_utama` tidak ada di $data, jadi tidak bisa ikut berubah.
        $alamat->update($this->validasi($request, $ditawarkan, $alamat));

        return response()->json([
            'message' => 'Alamat berhasil diperbarui',
            'data' => $alamat->refresh()->ringkas(),
        ]);
    }

    /**
     * FR-PROF-01 #6: minimal satu alamat harus tersisa. Kalau yang dihapus
     * alamat utama, alamat terlama dipromosikan supaya default tidak hilang.
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
     * FR-PROF-01 #7. Lepas alamat lain dulu: PostgreSQL memeriksa unique index
     * segera, bukan di akhir transaksi.
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
     * NFR-SEC-03: alamat orang lain dijawab 404, bukan 403, supaya
     * keberadaannya tidak terbuka.
     */
    private function milikUser(User $user, int $id): ?Address
    {
        return $user->addresses()->whereKey($id)->first();
    }

    /**
     * @param  Address|null  $alamat  Alamat yang sedang diubah; null saat menambah.
     * @return array<string, mixed>
     */
    private function validasi(Request $request, LokasiDitawarkan $ditawarkan, ?Address $alamat = null): array
    {
        // `no_hp` tidak dirapikan karena formatnya sudah diperiksa NomorTelepon.
        foreach (['nama_penerima', 'alamat_lengkap', 'kode_lokasi'] as $field) {
            $nilai = $request->input($field);

            if (is_string($nilai)) {
                $request->merge([$field => trim($nilai)]);
            }
        }

        $data = $request->validate([
            'nama_penerima' => ['required', 'string', 'max:255'],
            // Nomor penerima bisa berbeda dari nomor akun, jadi tetap diminta.
            'no_hp' => ['required', 'string', new NomorTelepon],
            'alamat_lengkap' => ['required', 'string'],
            'kode_lokasi' => ['required', 'string', 'max:255'],
            // Diterima demi bentuk request yang tidak berubah, tetapi nilainya
            // tidak dipakai: label selalu dari hasil pencarian.
            'label_lokasi' => ['sometimes', 'string', 'max:255'],
        ], [
            // Pesan turunan pola "Nama wajib diisi" (#10); dokumen hanya
            // menetapkan pesan format nomor telepon (#3).
            'nama_penerima.required' => 'Nama penerima wajib diisi',
            'no_hp.required' => 'Nomor telepon wajib diisi',
            'alamat_lengkap.required' => 'Alamat lengkap wajib diisi',
            'kode_lokasi.required' => 'Lokasi wajib dipilih',
        ]);

        $kode = $data['kode_lokasi'];
        $label = $this->labelSah($kode, $ditawarkan, $alamat);

        if ($label === null) {
            throw ValidationException::withMessages([
                'kode_lokasi' => self::PESAN_LOKASI_TIDAK_DIKENAL,
            ]);
        }

        // Label versi server, supaya tulisan yang tampil di daftar alamat dan
        // disalin ke pesanan tidak bisa dipalsukan.
        $data['label_lokasi'] = $label;

        return $data;
    }

    /**
     * Tawaran cache dulu; kalau kedaluwarsa, pasangan yang sudah tersimpan di
     * alamat itu tetap sah (skenario #5 hanya minta memilih ulang "bila perlu").
     */
    private function labelSah(string $kode, LokasiDitawarkan $ditawarkan, ?Address $alamat): ?string
    {
        $label = $ditawarkan->label($kode);

        if ($label !== null) {
            return $label;
        }

        if ($alamat?->kode_lokasi === $kode) {
            return $alamat->label_lokasi;
        }

        return null;
    }

    private function tidakDitemukan(): JsonResponse
    {
        return response()->json(['message' => self::PESAN_TIDAK_DITEMUKAN], 404);
    }
}
