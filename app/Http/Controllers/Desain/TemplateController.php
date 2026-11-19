<?php

namespace App\Http\Controllers\Desain;

use App\Http\Controllers\Controller;
use App\Models\Produk\Product;
use App\Models\Template;
use App\Support\GambarPublik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * FR-CDSN-01, UC-15, NFR-FIL-02: Admin Ecommerce mengelola template desain —
 * gambar dasar, ukuran kanvas, dan batas area kustomisasi per produk custom.
 */
class TemplateController extends Controller
{
    private const DISK = 'public';

    private const FOLDER = 'template';

    private const PESAN_FORMAT = 'Format file template harus PNG, JPG, atau SVG';

    private const PESAN_UKURAN = 'Ukuran file template maksimal 10 MB';

    private const PESAN_BUKAN_CUSTOM = 'Template hanya dapat dikaitkan ke produk custom';

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

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'nama' => ['required', 'string', 'max:255'],
            // NFR-FIL-02: PNG/JPG/SVG maksimal 10 MB (10240 kilobyte).
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,svg', 'max:10240'],
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

        $this->pastikanAreaValid($data);

        return $data;
    }

    /**
     * FR-CDSN-01 #2: editor membatasi elemen dengan keempat angka ini, jadi
     * area setengah jadi atau yang keluar kanvas tidak boleh tersimpan.
     *
     * @param  array<string, mixed>  $data
     */
    private function pastikanAreaValid(array &$data): void
    {
        $kolom = ['area_x', 'area_y', 'area_lebar', 'area_tinggi'];

        foreach ($kolom as $nama) {
            $data[$nama] ??= null;
        }

        $terisi = array_filter($kolom, fn (string $nama) => $data[$nama] !== null);

        if ($terisi === []) {
            return;
        }

        if (count($terisi) !== count($kolom)) {
            throw ValidationException::withMessages([
                'area_x' => 'Batas area kustomisasi harus diisi lengkap: x, y, lebar, dan tinggi',
            ]);
        }

        $kanvas = $data['lebar_kanvas'].' x '.$data['tinggi_kanvas'];
        $melewatiKanan = $data['lebar_kanvas'] < $data['area_x'] + $data['area_lebar'];
        $melewatiBawah = $data['tinggi_kanvas'] < $data['area_y'] + $data['area_tinggi'];

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
