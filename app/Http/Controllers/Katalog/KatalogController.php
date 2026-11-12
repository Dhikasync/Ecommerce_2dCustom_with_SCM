<?php

namespace App\Http\Controllers\Katalog;

use App\Http\Controllers\Controller;
use App\Models\Produk\Product;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * FR-KTLG-01, UC-12. Etalase publik: hanya produk aktif, harga dari varian
 * termurah, status stok dihitung dari Tersedia (Fisik - Direservasi).
 */
class KatalogController extends Controller
{
    private const PESAN_KOSONG = 'Belum ada produk tersedia';

    private const PER_HALAMAN_BAWAAN = 12;

    private const PER_HALAMAN_MAKSIMAL = 50;

    private const DISK = 'public';

    public function daftar(Request $request): JsonResponse
    {
        $produk = Product::query()
            ->where('is_aktif', true)
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->withMin('variants', 'harga')
            ->withMin('variants', 'moq')
            // Produk tanpa varian berarti tidak bisa dibeli, jadi `habis`.
            ->withExists(['variants as punya_stok' => fn ($query) => $query
                ->whereRaw('stok_fisik - stok_direservasi > 0')])
            ->orderBy('nama')
            ->paginate($this->perHalaman($request));

        $data = array_map(fn (Product $item) => $this->kartu($item), $produk->items());

        $meta = [
            'current_page' => $produk->currentPage(),
            'per_page' => $produk->perPage(),
            'total' => $produk->total(),
            'last_page' => $produk->lastPage(),
        ];

        return response()->json(
            $meta['total'] === 0
                ? ['message' => self::PESAN_KOSONG, 'data' => $data, 'meta' => $meta]
                : ['data' => $data, 'meta' => $meta]
        );
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
