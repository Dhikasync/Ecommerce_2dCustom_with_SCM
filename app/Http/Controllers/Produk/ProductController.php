<?php

namespace App\Http\Controllers\Produk;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Produk\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * FR-PROD-01, UC-09: Admin Ecommerce mengelola produk jadi dan produk custom
 * beserta harga, berat, dan gambar (srs.md:414).
 *
 * Dua aturan dokumen yang paling menentukan bentuk controller ini:
 *
 * 1. `srs.md:404` gambar produk hanya JPG/PNG maksimal 2 MB per file
 *    (NFR-FIL-01). Berkas pertama menjadi `products.gambar` — foto utama
 *    pada kartu katalog — dan sisanya masuk `product_images` sebagai galeri
 *    detail produk. Jumlah gambar tidak dibatasi dokumen.
 * 2. `user-story:222` produk custom harus dikaitkan dengan template desain.
 *    Template hidup di tabel `templates` dengan `product_id`, jadi template
 *    lahir SETELAH produk. Karena itu FR-PROD-01 tidak menerima template:
 *    produk custom dibuat Nonaktif dulu (`user-story:221` tetap terpenuhi
 *    untuk produk jadi), lalu diaktifkan saat template dan SLA sudah ada di
 *    modul Desain. Database sendiri menolak produk custom aktif tanpa SLA
 *    (`products_custom_aktif_check`).
 *
 * Stok tidak pernah disentuh di sini: varian dibuat berstok 0 dan angka stok
 * hanya diubah Admin SCM (srs.md:398, FR-SCM-01).
 */
class ProductController extends Controller
{
    private const PESAN_TIDAK_DITEMUKAN = 'Produk tidak ditemukan';

    private const PESAN_GAMBAR_TIDAK_DITEMUKAN = 'Gambar tidak ditemukan';

    private const PESAN_PESANAN_AKTIF = 'Produk masih memiliki pesanan aktif dan tidak dapat dihapus';

    /**
     * Pesanan berstatus ini dianggap selesai, sehingga produknya boleh
     * dihapus (FR-PROD-01 #5). Status lain berarti pesanan masih berjalan.
     */
    private const STATUS_PESANAN_SELESAI = ['selesai', 'dibatalkan'];

    private const DISK = 'public';

    private const FOLDER = 'produk';

    /**
     * Daftar produk untuk halaman "Kelola Produk".
     *
     * `harga_mulai` mengikuti FR-KTLG-01 #5: harga termurah dari varian.
     * Kalau produk belum punya varian, `harga_dasar` yang dipakai.
     */
    public function daftar(): JsonResponse
    {
        $produk = Product::query()
            ->with(['category:id,nama', 'images'])
            ->withCount('variants')
            ->withMin('variants', 'harga')
            ->orderBy('nama')
            ->get()
            ->map(fn (Product $item) => $this->ringkas($item));

        return response()->json(['data' => $produk]);
    }

    /**
     * FR-PROD-01 #1, #2, #7: tambah produk beserta gambarnya.
     */
    public function simpan(Request $request): JsonResponse
    {
        $data = $this->validasi($request);
        $berkas = $request->file('gambar', []);

        $produk = DB::transaction(function () use ($data, $berkas) {
            $produk = Product::create($data);
            $this->simpanGambar($produk, $berkas);

            return $produk;
        });

        return response()->json([
            'message' => 'Produk berhasil ditambahkan',
            'data' => $this->ringkas($produk->fresh(['category', 'images', 'variants']), true),
        ], 201);
    }

    /**
     * FR-PROD-01 #4, #6: ubah data produk atau ubah status Aktif/Nonaktif.
     */
    public function ubah(Request $request, int $id): JsonResponse
    {
        $produk = Product::with('images')->find($id);

        if (! $produk) {
            return $this->tidakDitemukan();
        }

        $data = $this->validasi($request);
        $berkas = $request->file('gambar', []);

        DB::transaction(function () use ($produk, $data, $berkas) {
            $produk->update($data);
            $this->simpanGambar($produk, $berkas);
        });

        return response()->json([
            'message' => 'Produk berhasil diubah',
            'data' => $this->ringkas($produk->fresh(['category', 'images', 'variants']), true),
        ]);
    }

    /**
     * FR-PROD-01 #5: hapus produk. Pesanan lama tetap terbaca karena
     * `order_items` menyimpan salinan nama produk, SKU, dan harga.
     *
     * Produk yang masih dipakai pesanan berjalan ditolak: menghapusnya akan
     * melepas tautan varian dari pesanan yang belum selesai.
     */
    public function hapus(int $id): JsonResponse
    {
        $produk = Product::with('images')->find($id);

        if (! $produk) {
            return $this->tidakDitemukan();
        }

        $varianIds = $produk->variants()->pluck('id');

        $punyaPesananAktif = OrderItem::query()
            ->whereIn('variant_id', $varianIds)
            ->whereHas('order', fn ($query) => $query->whereNotIn('status', self::STATUS_PESANAN_SELESAI))
            ->exists();

        if ($punyaPesananAktif) {
            return response()->json(['message' => self::PESAN_PESANAN_AKTIF], 422);
        }

        $berkas = $this->daftarBerkas($produk);

        // Varian, gambar, dan baris keranjang ikut terhapus lewat ON DELETE
        // CASCADE di migrasi masing-masing.
        $produk->delete();

        Storage::disk(self::DISK)->delete($berkas);

        return response()->json(['message' => 'Produk berhasil dihapus']);
    }

    /**
     * Hapus satu foto galeri. Foto utama (`products.gambar`) tidak bisa
     * dihapus lewat sini karena kartu katalog membutuhkannya; ganti foto
     * utama dengan mengunggah gambar baru lewat ubah produk.
     */
    public function hapusGambar(int $id, int $gambarId): JsonResponse
    {
        $produk = Product::find($id);

        if (! $produk) {
            return $this->tidakDitemukan();
        }

        $gambar = $produk->images()->find($gambarId);

        if (! $gambar) {
            return response()->json(['message' => self::PESAN_GAMBAR_TIDAK_DITEMUKAN], 404);
        }

        $berkas = $gambar->gambar;
        $gambar->delete();

        Storage::disk(self::DISK)->delete($berkas);

        return response()->json(['message' => 'Gambar berhasil dihapus']);
    }

    private function tidakDitemukan(): JsonResponse
    {
        return response()->json(['message' => self::PESAN_TIDAK_DITEMUKAN], 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request): array
    {
        // Nama dan deskripsi yang hanya berisi spasi dianggap kosong.
        foreach (['nama', 'deskripsi'] as $field) {
            $nilai = $request->input($field);

            if (is_string($nilai)) {
                $request->merge([$field => trim($nilai)]);
            }
        }

        $data = $request->validate([
            // `exists` memastikan kategori benar-benar ada; FK di database
            // tetap menjadi penjaga terakhir.
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'nama' => ['required', 'string', 'max:255'],
            // Kolom `deskripsi` di migrasi products bertipe NOT NULL.
            'deskripsi' => ['required', 'string'],
            'tipe' => ['required', 'in:jadi,custom'],
            'harga_dasar' => ['required', 'integer', 'min:0'],
            'is_aktif' => ['sometimes', 'boolean'],

            // Gambar tidak wajib. Bentuk array hanya dipakai kalau ada file
            // yang diunggah, jadi produk boleh disimpan tanpa gambar.
            'gambar' => ['sometimes', 'array'],
            // max:2048 dalam kilobyte = 2 MB (NFR-FIL-01).
            'gambar.*' => ['file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        // `gambar` berisi objek UploadedFile, bukan kolom database. Kalau ikut
        // diteruskan ke Product::create(), PDO mencoba menyimpan array dan
        // gagal. Berkasnya ditangani simpanGambar().
        unset($data['gambar']);

        // Produk custom selalu lahir Nonaktif: template (FR-CDSN-01) dan SLA
        // (FR-CDSN-07) belum ada, dan database menolak custom aktif tanpa
        // `sla_hari`. Aktivasinya dilakukan di modul Desain.
        if ($data['tipe'] === 'custom') {
            $data['is_aktif'] = false;
        }

        return $data;
    }

    /**
     * Menyimpan berkas unggahan: file pertama menjadi foto utama, sisanya
     * menjadi galeri berurutan. Foto utama lama dihapus hanya setelah berkas
     * baru benar-benar tersimpan.
     *
     * @param  array<int, UploadedFile>  $berkas
     */
    private function simpanGambar(Product $produk, array $berkas): void
    {
        if ($berkas === []) {
            return;
        }

        $utamaLama = $produk->gambar;
        $utamaBaru = null;
        $urutan = (int) $produk->images()->max('urutan');

        foreach ($berkas as $index => $file) {
            $path = $file->store(self::FOLDER, self::DISK);

            if ($index === 0) {
                $utamaBaru = $path;

                continue;
            }

            $urutan++;
            $produk->images()->create([
                'gambar' => $path,
                'urutan' => $urutan,
            ]);
        }

        $produk->update(['gambar' => $utamaBaru]);

        if ($utamaLama !== null && $utamaLama !== $utamaBaru) {
            Storage::disk(self::DISK)->delete($utamaLama);
        }
    }

    /**
     * @return array<int, string>
     */
    private function daftarBerkas(Product $produk): array
    {
        return collect([$produk->gambar])
            ->merge($produk->images->pluck('gambar'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function urlGambar(?string $path): ?string
    {
        return $path === null ? null : Storage::disk(self::DISK)->url($path);
    }

    /**
     * Bentuk produk yang dikirim ke API.
     *
     * @return array<string, mixed>
     */
    private function ringkas(Product $produk, bool $denganGaleri = false): array
    {
        $hargaMulai = $produk->variants_min_harga
            ?? ($produk->relationLoaded('variants') && $produk->variants->isNotEmpty()
                ? $produk->variants->min('harga')
                : null);

        $hasil = [
            'id' => $produk->id,
            'nama' => $produk->nama,
            'deskripsi' => $produk->deskripsi,
            'tipe' => $produk->tipe,
            'harga_dasar' => $produk->harga_dasar,
            'harga_mulai' => $hargaMulai ?? $produk->harga_dasar,
            'is_aktif' => $produk->is_aktif,
            'sla_hari' => $produk->sla_hari,
            'kategori' => [
                'id' => $produk->category?->id,
                'nama' => $produk->category?->nama,
            ],
            'gambar' => $produk->gambar,
            'gambar_url' => $this->urlGambar($produk->gambar),
            'jumlah_varian' => $produk->variants_count
                ?? ($produk->relationLoaded('variants') ? $produk->variants->count() : null),
        ];

        if ($denganGaleri) {
            $hasil['galeri'] = $produk->images
                ->map(fn ($gambar) => [
                    'id' => $gambar->id,
                    'urutan' => $gambar->urutan,
                    'url' => $this->urlGambar($gambar->gambar),
                ])
                ->values()
                ->all();
        }

        return $hasil;
    }
}
