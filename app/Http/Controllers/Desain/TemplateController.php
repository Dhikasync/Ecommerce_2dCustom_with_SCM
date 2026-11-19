<?php

namespace App\Http\Controllers\Desain;

use App\Http\Controllers\Controller;
use App\Models\Produk\Product;
use App\Models\Template;
use App\Support\GambarPublik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * FR-CDSN-01, UC-15, NFR-FIL-02: Admin Ecommerce mengelola template desain —
 * gambar dasar, ukuran kanvas, dan batas area kustomisasi per produk custom.
 */
class TemplateController extends Controller
{
    private const DISK = 'public';

    private const FOLDER = 'template';

    private const PESAN_TIDAK_DITEMUKAN = 'Template tidak ditemukan';

    private const PESAN_FORMAT = 'Format file template harus PNG, JPG, atau SVG';

    private const PESAN_UKURAN = 'Ukuran file template maksimal 10 MB';

    private const PESAN_BUKAN_CUSTOM = 'Template hanya dapat dikaitkan ke produk custom';

    private const PESAN_MASIH_DIPAKAI = 'Template masih dipakai desain User. Nonaktifkan saja, jangan dihapus';

    public function daftar(Request $request): JsonResponse
    {
        $produkId = $request->integer('product_id');

        $template = Template::query()
            ->with('product:id,nama')
            ->when($produkId > 0, fn ($kueri) => $kueri->where('product_id', $produkId))
            // Admin melihat semua template; penyaring dipakai pemilih template
            // saat User membuka editor (FR-CDSN-01 #4).
            ->when($request->has('is_aktif'), fn ($kueri) => $kueri->where('is_aktif', $request->boolean('is_aktif')))
            ->orderBy('nama')
            ->orderBy('id')
            ->get()
            ->map(fn (Template $item) => $this->ringkas($item));

        return response()->json(['data' => $template]);
    }

    /** FR-CDSN-01 #1/#2/#5/#6. */
    public function simpan(Request $request): JsonResponse
    {
        $data = $this->validasi($request);
        $file = $request->file('file');

        $data['file'] = $file->store(self::FOLDER, self::DISK);

        // `fresh()` membaca kembali baris agar default database (`is_aktif`)
        // ikut tampil di respons.
        $template = Template::create($data)->fresh('product');

        return response()->json([
            'message' => 'Template berhasil ditambahkan',
            'data' => $this->ringkas($template),
        ], 201);
    }

    /** FR-CDSN-01 #3: ubah file/kanvas/area tanpa menyentuh desain User. */
    public function ubah(Request $request, int $id): JsonResponse
    {
        $template = Template::find($id);

        if (! $template) {
            return $this->tidakDitemukan();
        }

        $data = $this->validasi($request, $template);
        $file = $request->file('file');
        $berkasLama = $template->file;

        DB::transaction(function () use ($template, $data, $file) {
            if ($file !== null) {
                $data['file'] = $file->store(self::FOLDER, self::DISK);
            }

            $template->update($data);
        });

        // Berkas lama hanya dibuang kalau tidak ada desain yang masih memakai
        // template ini (FR-CDSN-01 #3).
        if ($file !== null && $berkasLama !== $template->file && ! $template->designs()->exists()) {
            Storage::disk(self::DISK)->delete($berkasLama);
        }

        return response()->json([
            'message' => 'Template berhasil diubah',
            'data' => $this->ringkas($template->fresh('product')),
        ]);
    }

    /** FR-CDSN-01 #4: hapus template yang belum dipakai desain User. */
    public function hapus(int $id): JsonResponse
    {
        $template = Template::find($id);

        if (! $template) {
            return $this->tidakDitemukan();
        }

        if ($template->designs()->exists()) {
            return response()->json(['message' => self::PESAN_MASIH_DIPAKAI], 422);
        }

        $berkas = $template->file;

        $template->delete();
        Storage::disk(self::DISK)->delete($berkas);

        return response()->json(['message' => 'Template berhasil dihapus']);
    }

    private function tidakDitemukan(): JsonResponse
    {
        return response()->json(['message' => self::PESAN_TIDAK_DITEMUKAN], 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?Template $template = null): array
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'nama' => ['required', 'string', 'max:255'],
            // NFR-FIL-02: PNG/JPG/SVG maksimal 10 MB (10240 kilobyte).
            'file' => [
                $template === null ? 'required' : 'sometimes',
                'file',
                'mimes:png,jpg,jpeg,svg',
                'max:10240',
            ],
            'lebar_kanvas' => ['required', 'integer', 'min:1'],
            'tinggi_kanvas' => ['required', 'integer', 'min:1'],
            'area_x' => ['nullable', 'integer', 'min:0'],
            'area_y' => ['nullable', 'integer', 'min:0'],
            'area_lebar' => ['nullable', 'integer', 'min:1'],
            'area_tinggi' => ['nullable', 'integer', 'min:1'],
            'is_aktif' => ['sometimes', 'boolean'],
        ], [
            'file.mimes' => self::PESAN_FORMAT,
            'file.max' => self::PESAN_UKURAN,
        ]);

        // FR-CDSN-01 #6: template hanya bermakna untuk produk custom.
        if (! Product::find($data['product_id'])?->isCustom()) {
            throw ValidationException::withMessages([
                'product_id' => self::PESAN_BUKAN_CUSTOM,
            ]);
        }

        $this->pastikanAreaValid($data, $template);

        return $data;
    }

    /**
     * FR-CDSN-01 #2: editor membatasi elemen dengan keempat angka ini, jadi
     * area setengah jadi atau yang keluar kanvas tidak boleh tersimpan. Saat
     * mengubah, kolom yang tidak dikirim diambil dari nilai tersimpan.
     *
     * @param  array<string, mixed>  $data
     */
    private function pastikanAreaValid(array $data, ?Template $template = null): void
    {
        $nilai = fn (string $kolom) => array_key_exists($kolom, $data)
            ? $data[$kolom]
            : $template?->{$kolom};

        $area = [
            'area_x' => $nilai('area_x'),
            'area_y' => $nilai('area_y'),
            'area_lebar' => $nilai('area_lebar'),
            'area_tinggi' => $nilai('area_tinggi'),
        ];

        $terisi = array_filter($area, fn ($angka) => $angka !== null);

        if ($terisi === []) {
            return;
        }

        if (count($terisi) !== count($area)) {
            throw ValidationException::withMessages([
                'area_x' => 'Batas area kustomisasi harus diisi lengkap: x, y, lebar, dan tinggi',
            ]);
        }

        $kanvas = $nilai('lebar_kanvas').' x '.$nilai('tinggi_kanvas');
        $melewatiKanan = $nilai('lebar_kanvas') < $area['area_x'] + $area['area_lebar'];
        $melewatiBawah = $nilai('tinggi_kanvas') < $area['area_y'] + $area['area_tinggi'];

        if ($melewatiKanan || $melewatiBawah) {
            throw ValidationException::withMessages([
                'area_x' => 'Batas area kustomisasi harus berada di dalam kanvas '.$kanvas,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkas(Template $template): array
    {
        return [
            'id' => $template->id,
            'nama' => $template->nama,
            'file' => $template->file,
            'file_url' => GambarPublik::url($template->file),
            'lebar_kanvas' => $template->lebar_kanvas,
            'tinggi_kanvas' => $template->tinggi_kanvas,
            'area_x' => $template->area_x,
            'area_y' => $template->area_y,
            'area_lebar' => $template->area_lebar,
            'area_tinggi' => $template->area_tinggi,
            'is_aktif' => $template->is_aktif,
            'produk' => [
                'id' => $template->product?->id,
                'nama' => $template->product?->nama,
            ],
        ];
    }
}
