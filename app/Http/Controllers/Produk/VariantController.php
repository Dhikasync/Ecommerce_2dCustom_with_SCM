<?php

namespace App\Http\Controllers\Produk;

use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use App\Models\Notification;
use App\Models\OrderItem;
use App\Models\Produk\Product;
use App\Models\Produk\Variant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * FR-PROD-01 #3/#8 dan FR-PROD-03 #1, UC-09/UC-11: Admin Ecommerce mengelola
 * varian (SKU) beserta harga, berat satuan, dan MOQ.
 *
 * Aturan dokumen yang menentukan bentuk controller ini:
 *
 * - `user-story:218`: setiap varian disimpan sebagai SKU terpisah dengan kode
 *   unik dan **stok awal 0**; Admin Ecommerce tidak mengisi atau mengubah
 *   angka stok. Karena itu `stok_fisik`, `stok_direservasi`, dan
 *   `stok_minimum` tidak pernah dibaca dari request — kolomnya dibiarkan
 *   memakai default database (0) dan hanya Admin SCM yang boleh mengubahnya
 *   (srs.md:398, FR-SCM-01).
 * - `user-story:218`: berat satuan **wajib** karena dipakai menghitung ongkir.
 * - `user-story:223`: SKU duplikat ditolak dengan kalimat persis
 *   "Kode SKU sudah digunakan".
 * - FR-PROD-03 #1: MOQ disimpan pada tingkat varian. Kalau Admin tidak
 *   mengisinya, dipakai 1 — nilai terkecil yang diizinkan
 *   (`variants_moq_check`).
 * - `srs.md:405`: varian baru berstok 0 dan Stok Habis sampai Admin SCM
 *   menginput stok awal, sehingga Admin SCM dinotifikasi saat varian dibuat.
 *
 * Varian selalu dibaca lewat produknya. Ini mencegah satu produk mengubah
 * atau menghapus varian milik produk lain hanya dengan menebak id.
 */
class VariantController extends Controller
{
    private const PESAN_SKU_DUPLIKAT = 'Kode SKU sudah digunakan';

    private const PESAN_TIDAK_DITEMUKAN = 'Varian tidak ditemukan';

    private const PESAN_PRODUK_TIDAK_DITEMUKAN = 'Produk tidak ditemukan';

    private const PESAN_STOK_MASIH_ADA = 'Varian masih memiliki stok, kosongkan stok terlebih dahulu';

    private const PESAN_PESANAN_AKTIF = 'Varian masih memiliki pesanan aktif dan tidak dapat dihapus';

    private const STATUS_PESANAN_SELESAI = ['selesai', 'dibatalkan'];

    /**
     * Daftar varian milik satu produk, urut SKU.
     */
    public function daftar(int $id): JsonResponse
    {
        $produk = Product::find($id);

        if (! $produk) {
            return $this->produkTidakDitemukan();
        }

        $varian = $produk->variants()
            ->orderBy('sku')
            ->get()
            ->map(fn (Variant $item) => $this->ringkas($item))
            ->all();

        return response()->json(['data' => $varian]);
    }

    /**
     * FR-PROD-01 #3: tambah varian baru berstok 0 dan beri tahu Admin SCM.
     */
    public function simpan(Request $request, int $id): JsonResponse
    {
        $produk = Product::find($id);

        if (! $produk) {
            return $this->produkTidakDitemukan();
        }

        $data = $this->validasi($request);

        $varian = DB::transaction(function () use ($produk, $data) {
            $varian = $produk->variants()->create([
                ...$data,
                // Angka stok tidak pernah datang dari Admin Ecommerce.
                'stok_fisik' => 0,
                'stok_direservasi' => 0,
            ]);

            $this->beritahuAdminScm($varian, $produk);

            return $varian;
        });

        return response()->json([
            'message' => 'Varian berhasil ditambahkan',
            'data' => $this->ringkas($varian->refresh()),
        ], 201);
    }

    /**
     * FR-PROD-01 #3 dan FR-PROD-03 #1: ubah atribut varian atau MOQ-nya.
     * Angka stok tidak ikut berubah walau dikirim di request.
     */
    public function ubah(Request $request, int $id, int $varianId): JsonResponse
    {
        $produk = Product::find($id);

        if (! $produk) {
            return $this->produkTidakDitemukan();
        }

        $varian = $produk->variants()->find($varianId);

        if (! $varian) {
            return $this->tidakDitemukan();
        }

        $varian->update($this->validasi($request, $varian));

        return response()->json([
            'message' => 'Varian berhasil diubah',
            'data' => $this->ringkas($varian->refresh()),
        ]);
    }

    /**
     * Menghapus varian ditolak selama masih ada stok atau pesanan berjalan.
     *
     * Stok adalah wilayah Admin SCM: menghapus varian yang masih berstok
     * ikut menghapus riwayat kartu stoknya (`stock_movements` memakai
     * ON DELETE CASCADE) sehingga angka stok hilang tanpa jejak.
     */
    public function hapus(int $id, int $varianId): JsonResponse
    {
        $produk = Product::find($id);

        if (! $produk) {
            return $this->produkTidakDitemukan();
        }

        $varian = $produk->variants()->find($varianId);

        if (! $varian) {
            return $this->tidakDitemukan();
        }

        if ($varian->stok_fisik > 0 || $varian->stok_direservasi > 0) {
            return response()->json(['message' => self::PESAN_STOK_MASIH_ADA], 422);
        }

        $punyaPesananAktif = OrderItem::query()
            ->where('variant_id', $varian->id)
            ->whereHas('order', fn ($query) => $query->whereNotIn('status', self::STATUS_PESANAN_SELESAI))
            ->exists();

        if ($punyaPesananAktif) {
            return response()->json(['message' => self::PESAN_PESANAN_AKTIF], 422);
        }

        $varian->delete();

        return response()->json(['message' => 'Varian berhasil dihapus']);
    }

    private function produkTidakDitemukan(): JsonResponse
    {
        return response()->json(['message' => self::PESAN_PRODUK_TIDAK_DITEMUKAN], 404);
    }

    private function tidakDitemukan(): JsonResponse
    {
        return response()->json(['message' => self::PESAN_TIDAK_DITEMUKAN], 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?Variant $varian = null): array
    {
        foreach (['sku', 'ukuran', 'warna', 'ketebalan'] as $field) {
            $nilai = $request->input($field);

            if (is_string($nilai)) {
                $request->merge([$field => trim($nilai)]);
            }
        }

        $data = $request->validate([
            'sku' => ['required', 'string', 'max:255'],
            'ukuran' => ['nullable', 'string', 'max:255'],
            'warna' => ['nullable', 'string', 'max:255'],
            'ketebalan' => ['nullable', 'string', 'max:255'],
            // `harga` boleh 0, `berat` tidak: berat dipakai menghitung ongkir
            // (user-story:218) dan constraint variants_berat_check menuntut > 0.
            'harga' => ['required', 'integer', 'min:0'],
            'berat' => ['required', 'integer', 'min:1'],
            'moq' => ['sometimes', 'integer', 'min:1'],
        ]);

        // Saat menambah, MOQ yang tidak diisi memakai 1. Saat mengubah,
        // MOQ yang tidak dikirim dibiarkan apa adanya.
        if (! $varian && ! array_key_exists('moq', $data)) {
            $data['moq'] = 1;
        }

        // SKU dibandingkan tanpa membedakan huruf besar/kecil, sama seperti
        // nama kategori: `TAS-1` dan `tas-1` dianggap kode yang sama. Unique
        // bawaan kolom `sku` tetap menjadi penjaga terakhir untuk kode yang
        // persis sama.
        $duplikat = Variant::query()
            ->whereRaw('lower(sku) = ?', [Str::lower($data['sku'])])
            ->when($varian, fn ($query) => $query->whereKeyNot($varian->getKey()))
            ->exists();

        if ($duplikat) {
            throw ValidationException::withMessages(['sku' => self::PESAN_SKU_DUPLIKAT]);
        }

        return $data;
    }

    /**
     * FR-PROD-01 #3, FR-SCM-01 #6: Admin SCM diberi tahu bahwa ada varian
     * baru yang menunggu input stok awal. Notifikasi in-app memakai tabel
     * `notifications` yang sudah ada; akun nonaktif tidak dikirimi.
     */
    private function beritahuAdminScm(Variant $varian, Product $produk): void
    {
        $penerima = User::query()
            ->where('role', 'admin_scm')
            ->where('is_aktif', true)
            ->pluck('id');

        foreach ($penerima as $userId) {
            Notification::create([
                'user_id' => $userId,
                'judul' => 'Varian baru menunggu stok awal',
                'pesan' => "Varian {$varian->sku} pada produk {$produk->nama} berstok 0. "
                    .'Input stok awal agar varian dapat dibeli.',
                'tipe' => 'stok_awal',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkas(Variant $varian): array
    {
        return [
            'id' => $varian->id,
            'product_id' => $varian->product_id,
            'sku' => $varian->sku,
            'ukuran' => $varian->ukuran,
            'warna' => $varian->warna,
            'ketebalan' => $varian->ketebalan,
            'harga' => $varian->harga,
            'berat' => $varian->berat,
            'moq' => $varian->moq,
            'stok_fisik' => $varian->stok_fisik,
            'stok_direservasi' => $varian->stok_direservasi,
            'stok_minimum' => $varian->stok_minimum,
            // FR-SCM-01: Tersedia selalu dihitung, tidak pernah disimpan.
            'stok_tersedia' => $varian->stok_tersedia,
        ];
    }
}
