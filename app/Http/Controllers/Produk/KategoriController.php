<?php

namespace App\Http\Controllers\Produk;

use App\Http\Controllers\Controller;
use App\Models\Produk\Category;
use App\Models\Produk\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * FR-PROD-02, UC-10: Admin Ecommerce mengelola kategori produk.
 *
 * Aktornya hanya Admin Ecommerce (srs.md:398); pembatasan role ditegakkan
 * middleware `role` di routes/produk.php, bukan di controller ini.
 *
 * Nama kategori unik tanpa membedakan huruf besar/kecil. Itu keputusan di
 * luar dokumen (dicatat di README): dokumen hanya menulis "nama unik". Aturan
 * ini ditegakkan di sini, bukan lewat indeks tambahan di database, sehingga
 * pesan "Kategori sudah ada" (srs.md:406) muncul sebagai pesan validasi.
 * Unique bawaan kolom `nama` tetap menjadi penjaga terakhir untuk nama yang
 * persis sama.
 *
 * Kategori yang masih dipakai produk tidak boleh dihapus (srs.md:407).
 * Pemeriksaan di controller memberi pesan yang ditetapkan dokumen; FK
 * `products.category_id` yang memakai ON DELETE RESTRICT menjadi jaring
 * pengaman terakhir kalau pemeriksaan itu terlewat.
 */
class KategoriController extends Controller
{
    private const PESAN_DUPLIKAT = 'Kategori sudah ada';

    private const PESAN_TERPAKAI = 'Kategori masih digunakan oleh produk, pindahkan produk terlebih dahulu';

    private const PESAN_TIDAK_DITEMUKAN = 'Kategori tidak ditemukan';

    /**
     * Daftar kategori untuk halaman admin, diurutkan menurut nama.
     */
    public function daftar(): JsonResponse
    {
        $kategori = Category::orderBy('nama')
            ->get()
            ->map(fn (Category $item) => $this->ringkas($item))
            ->all();

        return response()->json(['data' => $kategori]);
    }

    /**
     * FR-PROD-02: tambah kategori baru.
     */
    public function simpan(Request $request): JsonResponse
    {
        $kategori = Category::create($this->validasi($request));

        return response()->json([
            'message' => 'Kategori berhasil ditambahkan',
            'data' => $this->ringkas($kategori),
        ], 201);
    }

    /**
     * FR-PROD-02: ubah nama atau deskripsi kategori.
     */
    public function ubah(Request $request, int $id): JsonResponse
    {
        $kategori = Category::find($id);

        if (! $kategori) {
            return $this->tidakDitemukan();
        }

        $kategori->update($this->validasi($request, $kategori));

        return response()->json([
            'message' => 'Kategori berhasil diubah',
            'data' => $this->ringkas($kategori->refresh()),
        ]);
    }

    /**
     * FR-PROD-02, srs.md:407: kategori yang masih dipakai produk ditolak.
     */
    public function hapus(int $id): JsonResponse
    {
        $kategori = Category::find($id);

        if (! $kategori) {
            return $this->tidakDitemukan();
        }

        if (Product::where('category_id', $kategori->id)->exists()) {
            return response()->json(['message' => self::PESAN_TERPAKAI], 422);
        }

        $kategori->delete();

        return response()->json(['message' => 'Kategori berhasil dihapus']);
    }

    private function tidakDitemukan(): JsonResponse
    {
        return response()->json(['message' => self::PESAN_TIDAK_DITEMUKAN], 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?Category $kategori = null): array
    {
        // Nama atau deskripsi yang hanya berisi spasi dianggap kosong.
        foreach (['nama', 'deskripsi'] as $field) {
            $nilai = $request->input($field);

            if (is_string($nilai)) {
                $request->merge([$field => trim($nilai)]);
            }
        }

        $data = $request->validate([
            // 255 mengikuti tipe `string` kolom `nama` di migrasi categories.
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
        ]);

        // Dibandingkan dalam huruf kecil supaya `Tas` dan `tas` dianggap sama.
        $duplikat = Category::query()
            ->whereRaw('lower(nama) = ?', [Str::lower($data['nama'])])
            ->when($kategori, fn ($query) => $query->whereKeyNot($kategori->getKey()))
            ->exists();

        if ($duplikat) {
            throw ValidationException::withMessages(['nama' => self::PESAN_DUPLIKAT]);
        }

        return $data;
    }

    /**
     * Bentuk kategori yang dikirim ke API.
     *
     * @return array<string, mixed>
     */
    private function ringkas(Category $kategori): array
    {
        return [
            'id' => $kategori->id,
            'nama' => $kategori->nama,
            'deskripsi' => $kategori->deskripsi,
        ];
    }
}
