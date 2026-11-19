<?php

namespace App\Http\Controllers\Produk;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Produk\Product;
use App\Support\GambarPublik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * FR-PROD-01, UC-09: Admin Ecommerce mengelola produk jadi dan custom.
 * Gambar pertama jadi `products.gambar`, sisanya galeri `product_images`.
 */
class ProductController extends Controller
{
    private const PESAN_TIDAK_DITEMUKAN = 'Produk tidak ditemukan';

    private const PESAN_GAMBAR_TIDAK_DITEMUKAN = 'Gambar tidak ditemukan';

    private const PESAN_PESANAN_AKTIF = 'Produk masih memiliki pesanan aktif dan tidak dapat dihapus';

    private const PESAN_STOK_MASIH_ADA = 'Produk masih memiliki stok pada salah satu varian, kosongkan stok terlebih dahulu';

    /** Status selain ini berarti pesanan masih berjalan (FR-PROD-01 #5). */
    private const STATUS_PESANAN_SELESAI = ['selesai', 'dibatalkan'];

    private const DISK = 'public';

    private const FOLDER = 'produk';

    /** `harga_mulai` = harga varian termurah, atau `harga_dasar` bila belum ada varian. */
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

    /** FR-PROD-01 #1, #2, #7. */
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

    /** FR-PROD-01 #4, #6. */
    public function ubah(Request $request, int $id): JsonResponse
    {
        $produk = Product::with('images')->find($id);

        if (! $produk) {
            return $this->tidakDitemukan();
        }

        $data = $this->validasi($request, $produk);
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
     * FR-PROD-01 #5. Ditolak juga saat varian berstok: CASCADE akan membuang
     * angka stok dan kartu stoknya, wilayah Admin SCM.
     */
    public function hapus(int $id): JsonResponse
    {
        $produk = Product::with('images')->find($id);

        if (! $produk) {
            return $this->tidakDitemukan();
        }

        $masihBerstok = $produk->variants()
            ->where(fn ($query) => $query
                ->where('stok_fisik', '>', 0)
                ->orWhere('stok_direservasi', '>', 0))
            ->exists();

        if ($masihBerstok) {
            return response()->json(['message' => self::PESAN_STOK_MASIH_ADA], 422);
        }

        $varianIds = $produk->variants()->pluck('id');

        $punyaPesananAktif = OrderItem::query()
            ->whereIn('variant_id', $varianIds)
            ->whereHas('order', fn ($query) => $query->whereNotIn('status', self::STATUS_PESANAN_SELESAI))
            ->exists();

        if ($punyaPesananAktif) {
            return response()->json(['message' => self::PESAN_PESANAN_AKTIF], 422);
        }

        $berkas = $produk->berkasGambar();

        $produk->delete();

        Storage::disk(self::DISK)->delete($berkas);

        return response()->json(['message' => 'Produk berhasil dihapus']);
    }

    /** Foto utama tidak bisa dihapus dari sini karena kartu katalog memakainya. */
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
     * @param  Product|null  $produk  Produk yang sedang diubah; null saat menambah.
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?Product $produk = null): array
    {
        // Nama dan deskripsi yang hanya berisi spasi dianggap kosong.
        foreach (['nama', 'deskripsi'] as $field) {
            $nilai = $request->input($field);

            if (is_string($nilai)) {
                $request->merge([$field => trim($nilai)]);
            }
        }

        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['required', 'string'],
            'tipe' => ['required', 'in:jadi,custom'],
            'harga_dasar' => ['required', 'integer', 'min:0'],
            'is_aktif' => ['sometimes', 'boolean'],

            // FR-CDSN-07 #1/#2: SLA hanya untuk custom, bilangan bulat hari kerja.
            'sla_hari' => ['required_if:tipe,custom', 'nullable', 'integer', 'min:1'],

            'gambar' => ['sometimes', 'array'],
            // max:2048 kilobyte = 2 MB (NFR-FIL-01).
            'gambar.*' => ['file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ], [
            // FR-CDSN-07 #2: dokumen memakai satu kalimat untuk semua bentuk
            // nilai salah, jadi `required_if`, `integer`, dan `min` sama bunyinya.
            ...array_fill_keys(
                ['sla_hari.required_if', 'sla_hari.integer', 'sla_hari.min'],
                'Estimasi waktu produksi wajib berupa angka hari kerja minimal 1',
            ),
        ]);

        // `gambar` berisi UploadedFile, bukan kolom database.
        unset($data['gambar']);

        // Produk jadi tidak punya produksi custom, jadi SLA tidak boleh
        // tertinggal dari kiriman form yang salah.
        if ($data['tipe'] === 'jadi') {
            $data['sla_hari'] = null;
        }

        // FR-CDSN-07 #4: permintaan aktivasi produk custom diperiksa
        // kelengkapannya. Tanpa permintaan, custom baru lahir Nonaktif dan
        // custom yang sudah aktif dibiarkan (mengubah nama/harga tidak
        // menurunkannya dari katalog).
        if ($data['tipe'] === 'custom') {
            $sudahAktif = $produk?->tipe === 'custom' && $produk->is_aktif;

            if (! array_key_exists('is_aktif', $data)) {
                $data['is_aktif'] = $sudahAktif;
            } elseif ($data['is_aktif']) {
                $this->pastikanLengkapUntukAktif($data, $produk);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pastikanLengkapUntukAktif(array $data, ?Product $produk): void
    {
        $kurang = [];

        if (($data['sla_hari'] ?? $produk?->sla_hari) === null) {
            $kurang[] = 'SLA';
        }

        $punyaTemplate = $produk !== null
            && $produk->templates()->where('is_aktif', true)->exists();

        if (! $punyaTemplate) {
            $kurang[] = 'template desain';
        }

        if ($kurang === []) {
            return;
        }

        throw ValidationException::withMessages([
            'is_aktif' => 'Produk custom belum lengkap: '.implode(', ', $kurang),
        ]);
    }

    /**
     * Foto utama lama dihapus hanya setelah berkas baru tersimpan.
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
            'gambar_url' => GambarPublik::url($produk->gambar),
            'jumlah_varian' => $produk->variants_count
                ?? ($produk->relationLoaded('variants') ? $produk->variants->count() : null),
        ];

        if ($denganGaleri) {
            $hasil['galeri'] = $produk->images
                ->map(fn ($gambar) => [
                    'id' => $gambar->id,
                    'urutan' => $gambar->urutan,
                    'url' => GambarPublik::url($gambar->gambar),
                ])
                ->values()
                ->all();
        }

        return $hasil;
    }
}
