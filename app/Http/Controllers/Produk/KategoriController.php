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
 * FR-PROD-02, UC-10: Admin Ecommerce mengelola kategori produk. Pembatasan
 * role ada di middleware `role` pada routes/produk.php.
 *
 * Nama kategori unik tanpa membedakan huruf besar/kecil — keputusan di luar
 * dokumen (README) — dan diperiksa di sini supaya pesan "Kategori sudah ada"
 * (srs.md:406) keluar sebagai pesan validasi.
 */
class KategoriController extends Controller
{
    private const PESAN_DUPLIKAT = 'Kategori sudah ada';

    private const PESAN_TERPAKAI = 'Kategori masih digunakan oleh produk, pindahkan produk terlebih dahulu';

    private const PESAN_TIDAK_DITEMUKAN = 'Kategori tidak ditemukan';

    public function daftar(): JsonResponse
    {
        $kategori = Category::orderBy('nama')
            ->get()
            ->map(fn (Category $item) => $this->ringkas($item))
            ->all();

        return response()->json(['data' => $kategori]);
    }

    /** FR-PROD-02. */
    public function simpan(Request $request): JsonResponse
    {
        $kategori = Category::create($this->validasi($request));

        return response()->json([
            'message' => 'Kategori berhasil ditambahkan',
            'data' => $this->ringkas($kategori),
        ], 201);
    }

    /** FR-PROD-02. */
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
     * srs.md:407. ON DELETE RESTRICT pada `products.category_id` menjadi
     * jaring pengaman terakhir kalau pemeriksaan ini terlewat.
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
        foreach (['nama', 'deskripsi'] as $field) {
            $nilai = $request->input($field);

            if (is_string($nilai)) {
                $request->merge([$field => trim($nilai)]);
            }
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
        ]);

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
