<?php

namespace App\Http\Controllers\Desain;

use App\Http\Controllers\Controller;
use App\Support\GambarPublik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * FR-CDSN-03 #1-#5, NFR-FIL-03: gambar unggahan User (logo/foto) untuk kanvas
 * editor kustomisasi. Tidak ada tabel unggahan di dokumen, jadi berkas hidup
 * di folder milik sesi dan daftar sementaranya dibaca dari folder itu; rujukan
 * permanennya baru lahir saat desain disimpan (FR-CDSN-02).
 */
class UnggahanController extends Controller
{
    private const DISK = 'public';

    private const FOLDER = 'desain/unggahan';

    private const KUNCI_PEMILIK = 'desain_unggah_owner';

    private const PESAN_UNGGAH = 'Gambar berhasil diunggah';

    private const PESAN_HAPUS = 'Gambar berhasil dihapus';

    private const PESAN_TIDAK_DITEMUKAN = 'Gambar tidak ditemukan';

    private const PESAN_KOSONG = 'Pilih gambar yang akan diunggah';

    private const PESAN_FORMAT = 'Format file tidak didukung, gunakan JPG/PNG';

    private const PESAN_UKURAN = 'Ukuran file melebihi batas maksimum';

    private const PESAN_RESOLUSI = 'Resolusi gambar rendah, hasil cetak mungkin kurang tajam';

    /** FR-CDSN-03 #4: ambang peringatan, bukan penolakan. */
    private const SISI_MINIMAL = 200;

    public function daftar(Request $request): JsonResponse
    {
        $data = collect(Storage::disk(self::DISK)->files($this->folder($request)))
            ->map(fn (string $berkas) => $this->ringkas($berkas))
            ->values();

        return response()->json(['data' => $data]);
    }

    /** FR-CDSN-03 #1: gambar masuk kanvas sebagai elemen baru. */
    public function simpan(Request $request): JsonResponse
    {
        $request->validate([
            // NFR-FIL-03: JPG/PNG maks 5 MB (max memakai kilobyte).
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ], [
            'file.required' => self::PESAN_KOSONG,
            'file.mimes' => self::PESAN_FORMAT,
            'file.max' => self::PESAN_UKURAN,
        ]);

        $berkas = $request->file('file')->store($this->folder($request), self::DISK);

        return response()->json([
            'message' => self::PESAN_UNGGAH,
            'data' => $this->ringkas($berkas),
        ], 201);
    }

    /** FR-CDSN-03 #5: hapus dari kanvas dan dari daftar unggahan sementara. */
    public function hapus(Request $request, string $nama): JsonResponse
    {
        // Nama dari URL dipotong jadi basename supaya `..` tidak bisa keluar
        // dari folder pemilik.
        $berkas = $this->folder($request).'/'.basename($nama);

        if (! Storage::disk(self::DISK)->exists($berkas)) {
            return response()->json(['message' => self::PESAN_TIDAK_DITEMUKAN], 404);
        }

        Storage::disk(self::DISK)->delete($berkas);

        return response()->json(['message' => self::PESAN_HAPUS]);
    }

    /**
     * Token pemilik ada di sesi, bukan di akun: Guest boleh mengustomisasi
     * (user-story.md:834 hanya membatasi keranjang, checkout, dan pesanan),
     * dan token bertahan saat sesi di-regenerate ketika Guest login.
     */
    private function folder(Request $request): string
    {
        $pemilik = $request->session()->get(self::KUNCI_PEMILIK);

        if (! is_string($pemilik) || $pemilik === '') {
            $pemilik = (string) Str::uuid();
            $request->session()->put(self::KUNCI_PEMILIK, $pemilik);
        }

        return self::FOLDER.'/'.$pemilik;
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkas(string $berkas): array
    {
        $ukuran = @getimagesize(Storage::disk(self::DISK)->path($berkas));
        $lebar = $ukuran[0] ?? null;
        $tinggi = $ukuran[1] ?? null;

        $rendah = $lebar !== null && $tinggi !== null
            && ($lebar < self::SISI_MINIMAL || $tinggi < self::SISI_MINIMAL);

        return [
            'berkas' => $berkas,
            'url' => GambarPublik::url($berkas),
            'lebar' => $lebar,
            'tinggi' => $tinggi,
            'peringatan' => $rendah ? self::PESAN_RESOLUSI : null,
        ];
    }
}
