<?php

namespace App\Http\Controllers\Katalog;

use App\Http\Controllers\Controller;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use App\Models\Produk\Variant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * FR-KTLG-01, UC-12: etalase publik, hanya produk aktif, harga dari varian
 * termurah. FR-KTLG-02, UC-13: detail produk. FR-KTLG-03, UC-14: filter, cari, urut.
 */
class KatalogController extends Controller
{
    private const PESAN_KOSONG = 'Belum ada produk tersedia';

    private const PESAN_TIDAK_DITEMUKAN = 'Produk tidak ditemukan';

    private const PESAN_FILTER_KOSONG = 'Tidak ada produk yang sesuai dengan filter ini';

    private const PER_HALAMAN_BAWAAN = 12;

    private const PER_HALAMAN_MAKSIMAL = 50;

    private const DISK = 'public';

    public function daftar(Request $request): JsonResponse
    {
        $kataKunci = $request->query('q');
        $kataKunci = is_string($kataKunci) ? trim($kataKunci) : '';

        $query = Product::query()
            ->where('is_aktif', true)
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->withMin('variants', 'harga')
            ->withMin('variants', 'moq')
            // Produk tanpa varian berarti tidak bisa dibeli, jadi `habis`.
            ->withExists(['variants as punya_stok' => fn ($varian) => $varian
                ->whereRaw('stok_fisik - stok_direservasi > 0')]);

        $adaFilter = $this->terapkanFilter($query, $request);

        if ($kataKunci !== '') {
            // `%` dan `_` adalah wildcard LIKE, jadi dicari sebagai teks biasa.
            $query->where('nama', 'ilike', '%'.addcslashes($kataKunci, '\\%_').'%');
        }

        $this->urutkan($query, $request);

        $produk = $query->paginate($this->perHalaman($request));

        $data = array_map(fn (Product $item) => $this->kartu($item), $produk->items());

        $meta = [
            'current_page' => $produk->currentPage(),
            'per_page' => $produk->perPage(),
            'total' => $produk->total(),
            'last_page' => $produk->lastPage(),
        ];

        if ($meta['total'] !== 0) {
            return response()->json(['data' => $data, 'meta' => $meta]);
        }

        return response()->json([
            'message' => $this->pesanKosong($kataKunci, $adaFilter),
            'data' => $data,
            'meta' => $meta,
        ]);
    }

    /** Daftar kategori untuk pilihan filter (FR-KTLG-03 #1), publik seperti katalog. */
    public function kategori(): JsonResponse
    {
        return response()->json([
            'data' => Category::query()->orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    /**
     * FR-KTLG-02 #1/#5, UC-13. Produk nonaktif dan id non-angka dijawab 404
     * dengan pesan yang sama, supaya link lama tidak membuka etalase.
     */
    public function detail(Request $request): JsonResponse
    {
        // Id non-angka tidak boleh sampai ke query PostgreSQL (error tipe).
        $id = filter_var($request->route('id'), FILTER_VALIDATE_INT);

        $produk = $id === false ? null : Product::query()
            ->where('is_aktif', true)
            ->with(['category', 'images', 'variants' => fn ($query) => $query->orderBy('id')])
            ->find($id);

        if ($produk === null) {
            return response()->json(['message' => self::PESAN_TIDAK_DITEMUKAN], 404);
        }

        return response()->json(['data' => $this->detailProduk($produk)]);
    }

    private function perHalaman(Request $request): int
    {
        $nilai = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);

        if ($nilai === false || $nilai < 1) {
            return self::PER_HALAMAN_BAWAAN;
        }

        return min($nilai, self::PER_HALAMAN_MAKSIMAL);
    }

    /**
     * FR-KTLG-03 #1/#2/#3.
     *
     * @return bool true bila ada filter yang diminta, sah maupun tidak
     */
    private function terapkanFilter(Builder $query, Request $request): bool
    {
        $ada = false;

        if ($request->filled('kategori_id')) {
            $ada = true;
            $kategoriId = filter_var($request->query('kategori_id'), FILTER_VALIDATE_INT);

            if ($kategoriId === false) {
                $this->tidakAdaHasil($query);
            } else {
                $query->where('category_id', $kategoriId);
            }
        }

        if ($request->filled('jenis')) {
            $ada = true;
            $jenis = $request->query('jenis');

            if (in_array($jenis, ['jadi', 'custom'], true)) {
                $query->where('tipe', $jenis);
            } else {
                $this->tidakAdaHasil($query);
            }
        }

        return $ada;
    }

    /** Nilai filter tidak sah dianggap filter yang tidak cocok (keputusan README). */
    private function tidakAdaHasil(Builder $query): void
    {
        $query->whereRaw('0 = 1');
    }

    /** Skenario #6: terendah ke tertinggi; parameter lain kembali ke urutan nama. */
    private function urutkan(Builder $query, Request $request): void
    {
        if ($request->query('urut') !== 'harga_asc') {
            $query->orderBy('nama');

            return;
        }

        // Kartu menampilkan harga varian termurah atau harga_dasar, jadi urutan
        // harga memakai angka yang sama; `nama` menjaga halaman tetap stabil.
        $query->orderByRaw(
            'coalesce((select min(v.harga) from variants v where v.product_id = products.id), products.harga_dasar) asc'
        )->orderBy('nama');
    }

    /** Tiga pesan kosong dokumen: pencarian, filter, lalu katalog yang memang kosong. */
    private function pesanKosong(string $kataKunci, bool $adaFilter): string
    {
        if ($kataKunci !== '') {
            return self::PESAN_TIDAK_DITEMUKAN;
        }

        return $adaFilter ? self::PESAN_FILTER_KOSONG : self::PESAN_KOSONG;
    }

    /**
     * @return array<string, mixed>
     */
    private function kartu(Product $produk): array
    {
        return [
            'id' => $produk->id,
            'nama' => $produk->nama,
            'gambar_url' => $this->urlGambar($produk->gambar),
            'tipe' => $produk->tipe,
            'is_custom' => $produk->isCustom(),
            'harga_mulai' => $produk->variants_min_harga ?? $produk->harga_dasar,
            'moq' => $produk->variants_min_moq,
            'status_stok' => $produk->punya_stok ? 'tersedia' : 'habis',
            'rating' => $produk->reviews_avg_rating === null
                ? null
                : (float) $produk->reviews_avg_rating,
            'jumlah_ulasan' => $produk->reviews_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailProduk(Product $produk): array
    {
        return [
            'id' => $produk->id,
            'nama' => $produk->nama,
            'deskripsi' => $produk->deskripsi,
            'tipe' => $produk->tipe,
            'is_custom' => $produk->isCustom(),
            'kategori' => [
                'id' => $produk->category?->id,
                'nama' => $produk->category?->nama,
            ],
            'harga_mulai' => $produk->variants->min('harga') ?? $produk->harga_dasar,
            'galeri' => $this->galeri($produk),
            'varian' => $produk->variants
                ->map(fn (Variant $varian) => $this->varian($varian))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function galeri(Product $produk): array
    {
        return array_map(fn (string $path) => $this->urlGambar($path), $produk->berkasGambar());
    }

    /**
     * @return array<string, mixed>
     */
    private function varian(Variant $varian): array
    {
        return [
            'id' => $varian->id,
            'sku' => $varian->sku,
            'ukuran' => $varian->ukuran,
            'warna' => $varian->warna,
            'ketebalan' => $varian->ketebalan,
            'harga' => $varian->harga,
            'berat' => $varian->berat,
            'moq' => $varian->moq,
            // Stok Tersedia = Fisik - Direservasi, accessor di model (FR-SCM-01).
            'stok_tersedia' => $varian->stok_tersedia,
        ];
    }

    private function urlGambar(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        // `url()` ada di kontrak Cloud/FilesystemAdapter, bukan di kontrak
        // Filesystem yang dijanjikan facade Storage.
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(self::DISK);

        return $disk->url($path);
    }
}
