<?php

namespace App\Http\Controllers\Desain;

use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\DesignRevision;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Produk\Product;
use App\Models\Produk\Variant;
use App\Models\Template;
use App\Support\GambarPublik;
use App\Support\UnggahanDesain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * FR-CDSN-02 #1/#9/#10, UC-17: draft desain custom milik User. Bentuk editor
 * (drag, resize, rotate, layer) hidup di klien; yang dijaga server adalah
 * bentuk elemen, batas area cetak, kepemilikan draft, dan rujukan berkas.
 */
class DesainController extends Controller
{
    private const DISK = 'public';

    private const PESAN_TIDAK_DITEMUKAN = 'Desain tidak ditemukan';

    private const PESAN_TIDAK_BISA_DIUBAH = 'Desain ini tidak dapat diubah lagi';

    private const PESAN_SEDANG_DICETAK = 'Desain tidak dapat diubah karena pesanan sedang dalam proses cetak';

    /** FR-CDSN-04 #4: dokumen hanya meminta "pesan error", kalimat ini dipilih di sini. */
    private const PESAN_GAGAL_RENDER = 'Preview gagal dimuat, periksa kembali elemen desain';

    private const PESAN_PREVIEW_DISIMPAN = 'Gambar preview disimpan';

    /** Lampiran B: draft dan desain yang diminta revisi masih boleh disunting. */
    private const STATUS_BISA_DIUBAH = ['draft', 'perlu_revisi'];

    /** FR-CDSN-02 #10: daftar draft milik User yang sedang login. */
    public function daftar(Request $request): JsonResponse
    {
        $data = Design::query()
            ->where('user_id', $request->user()->id)
            ->with('template:id,nama,file,lebar_kanvas,tinggi_kanvas,area_x,area_y,area_lebar,area_tinggi')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Design $desain) => $this->ringkas($desain, denganData: false));

        return response()->json(['data' => $data]);
    }

    /** FR-CDSN-02 #10: muat ulang elemen terakhir beserta catatan revisi. */
    public function tampil(Request $request, int $id): JsonResponse
    {
        $desain = $this->milikUser($request, $id);

        if ($desain === null) {
            return $this->tidakDitemukan();
        }

        return response()->json(['data' => $this->ringkas($desain, denganData: true)]);
    }

    /**
     * FR-CDSN-04 #1/#4, UC-19: data yang dipakai halaman preview untuk
     * menggambar gabungan template dan elemen. Hanya membaca, jadi semua
     * status boleh dilihat pemiliknya.
     */
    public function preview(Request $request, int $id): JsonResponse
    {
        $desain = $this->milikUser($request, $id);

        if ($desain === null) {
            return $this->tidakDitemukan();
        }

        $elemen = $this->elemenSiapRender($desain);

        if ($elemen === null) {
            return response()->json(['message' => self::PESAN_GAGAL_RENDER], 422);
        }

        return response()->json(['data' => [
            'id' => $desain->id,
            'status' => $desain->status,
            'versi' => $desain->versi,
            'kanvas' => [
                'lebar' => $desain->template?->lebar_kanvas,
                'tinggi' => $desain->template?->tinggi_kanvas,
            ],
            'template' => $desain->template === null ? null : $this->ringkasTemplate($desain->template),
            'elemen' => $elemen,
            'preview_gambar' => $desain->preview_gambar === null
                ? null
                : GambarPublik::url($desain->preview_gambar),
        ]]);
    }

    /**
     * FR-CDSN-04 #3: menyimpan gambar preview hasil render klien. Server tidak
     * bisa merender sendiri (tidak ada GD/Imagick), jadi berkasnya diterima
     * dari kanvas dan disimpan sebagai rujukan keranjang serta validasi Admin.
     */
    public function simpanPreview(Request $request, int $id): JsonResponse
    {
        $desain = $this->milikUser($request, $id);

        if ($desain === null) {
            return $this->tidakDitemukan();
        }

        if (! in_array($desain->status, self::STATUS_BISA_DIUBAH, true)) {
            return response()->json(['message' => self::PESAN_TIDAK_BISA_DIUBAH], 422);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:png', 'max:10240'],
        ], [
            'file.required' => 'Pilih gambar preview yang akan disimpan',
            'file.mimes' => 'Format file tidak didukung, gunakan PNG',
            'file.max' => 'Ukuran file melebihi batas maksimum',
        ]);

        $lama = $desain->preview_gambar;
        $berkas = $request->file('file')->store(UnggahanDesain::folderPreview($desain->id), self::DISK);

        $desain->update(['preview_gambar' => $berkas]);

        // Preview lama dilepas setelah yang baru tersimpan, supaya folder desain
        // tidak menumpuk gambar yang tidak lagi dipakai.
        if ($lama !== null && $lama !== $berkas) {
            Storage::disk(self::DISK)->delete($lama);
        }

        return response()->json([
            'message' => self::PESAN_PREVIEW_DISIMPAN,
            'data' => [
                'id' => $desain->id,
                'preview_gambar' => GambarPublik::url($berkas),
            ],
        ]);
    }

    /**
     * FR-CDSN-04 #1/#6: elemen diurutkan menurut `z` supaya klien menggambar
     * dari lapisan belakang ke depan tanpa menebak, dan gambar yang berkasnya
     * hilang/rusak membuat preview ditolak (#4) alih-alih tampil bolong.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function elemenSiapRender(Design $desain): ?array
    {
        $elemen = $desain->data_desain['elemen'] ?? [];

        usort($elemen, fn (array $a, array $b) => ($a['z'] ?? 0) <=> ($b['z'] ?? 0));

        foreach ($elemen as $indeks => $item) {
            if (($item['tipe'] ?? null) !== 'gambar') {
                continue;
            }

            $berkas = $item['berkas'] ?? null;

            if (! is_string($berkas) || $berkas === '') {
                return null;
            }

            $disk = Storage::disk(self::DISK);

            if (! $disk->exists($berkas) || @getimagesize($disk->path($berkas)) === false) {
                return null;
            }

            $elemen[$indeks]['url'] = GambarPublik::url($berkas);
        }

        return $elemen;
    }

    /**
     * FR-CDSN-02 #1/#10: pembuka editor dari halaman detail produk. Publik
     * supaya Guest boleh mengustomisasi; draft hanya disertakan bila User
     * sudah login dan pernah menyimpannya untuk template ini.
     */
    public function editor(Request $request, int $product): JsonResponse
    {
        $produk = Product::find($product);

        if ($produk === null) {
            return response()->json(['message' => 'Produk tidak ditemukan'], 404);
        }

        if (! $produk->isCustom()) {
            return response()->json(['message' => 'Editor hanya tersedia untuk produk custom'], 422);
        }

        $diminta = $request->integer('template_id');

        if ($diminta > 0) {
            $template = $produk->templates()
                ->whereKey($diminta)
                ->where('is_aktif', true)
                ->first();

            if ($template === null) {
                return response()->json(['message' => 'Template desain tidak tersedia'], 422);
            }
        } else {
            $template = $produk->templates()
                ->where('is_aktif', true)
                ->orderBy('id')
                ->first();

            if ($template === null) {
                return response()->json(['message' => 'Produk custom belum punya template desain aktif'], 422);
            }
        }

        $draft = $request->user() === null ? null : Design::query()
            ->where('user_id', $request->user()->id)
            ->where('template_id', $template->id)
            ->whereIn('status', self::STATUS_BISA_DIUBAH)
            ->with('template')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        return response()->json(['data' => [
            'produk' => [
                'id' => $produk->id,
                'nama' => $produk->nama,
                'tipe' => $produk->tipe,
            ],
            'template' => $this->ringkasTemplate($template),
            'draft' => $draft === null ? null : $this->ringkas($draft, denganData: true),
        ]]);
    }

    /** FR-CDSN-02 #9: "Simpan sebagai Draft". */
    public function simpan(Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        $desain = DB::transaction(function () use ($request, $data) {
            $desain = Design::create([
                'user_id' => $request->user()->id,
                'template_id' => $data['template_id'],
                'variant_id' => $data['variant_id'] ?? null,
                'data_desain' => ['elemen' => []],
                'status' => 'draft',
                'versi' => 1,
            ]);

            $desain->update([
                'data_desain' => ['elemen' => $this->pindahkanGambar($desain, $data['data_desain']['elemen'], $request)],
            ]);

            return $desain;
        });

        return response()->json([
            'message' => 'Desain disimpan sebagai draft',
            'data' => $this->ringkas($desain->fresh('template'), denganData: true),
        ], 201);
    }

    /** FR-CDSN-02 #9/#10, FR-CDSN-05 #4, FR-ORDR-03 #8: ubah desain. */
    public function ubah(Request $request, int $id): JsonResponse
    {
        $desain = $this->milikUser($request, $id);

        if ($desain === null) {
            return $this->tidakDitemukan();
        }

        $sedangDicetak = OrderItem::query()
            ->where('design_id', $desain->id)
            ->whereHas('order', fn ($query) => $query->where('status', 'sedang_dicetak'))
            ->exists();

        if ($sedangDicetak) {
            return response()->json(['message' => self::PESAN_SEDANG_DICETAK], 422);
        }

        if (! in_array($desain->status, self::STATUS_BISA_DIUBAH, true)) {
            return response()->json(['message' => self::PESAN_TIDAK_BISA_DIUBAH], 422);
        }

        $data = $this->validasi($request, $desain);

        DB::transaction(function () use ($desain, $data, $request) {
            $perubahan = [
                'data_desain' => ['elemen' => $this->pindahkanGambar($desain, $data['data_desain']['elemen'], $request)],
                'variant_id' => $data['variant_id'] ?? $desain->variant_id,
            ];

            if ($desain->status === 'perlu_revisi') {
                // FR-CDSN-05 #4/#5: desain kembali ke antrean dengan versi baru.
                $perubahan['status'] = 'menunggu_validasi';
                $perubahan['versi'] = $desain->versi + 1;

                $orderId = OrderItem::orderIdUntukDesain($desain->id);

                if ($orderId !== null) {
                    Order::query()
                        ->whereKey($orderId)
                        ->where('status', 'menunggu_revisi_desain')
                        ->update(['status' => 'menunggu_validasi']);
                }
            }

            $desain->update($perubahan);
        });

        return response()->json([
            'message' => 'Desain berhasil disimpan',
            'data' => $this->ringkas($desain->fresh('template'), denganData: true),
        ]);
    }

    /** FR-ORDR-03 #4: Admin menyunting desain pesanan sebelum cetak. */
    public function ubahOlehAdmin(Request $request, string $nomor): JsonResponse
    {
        $pesanan = Order::query()
            ->where('nomor_pesanan', $nomor)
            ->first();

        if ($pesanan === null) {
            return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
        }

        if ($pesanan->status === 'sedang_dicetak') {
            return response()->json(['message' => self::PESAN_SEDANG_DICETAK], 422);
        }

        $item = $pesanan->orderItems()->whereNotNull('design_id')->first();
        $desain = $item === null ? null : Design::with('template')->find($item->design_id);

        if ($desain === null) {
            return response()->json(['message' => self::PESAN_TIDAK_DITEMUKAN], 404);
        }

        $data = $this->validasi($request, $desain);

        DB::transaction(function () use ($desain, $data, $request) {
            DesignRevision::create([
                'design_id' => $desain->id,
                'versi' => $desain->versi,
                'data_desain' => $desain->data_desain,
                'preview_gambar' => $desain->preview_gambar,
                'status' => $desain->status,
                'divalidasi_oleh' => $request->user()->id,
            ]);

            $desain->update([
                'data_desain' => ['elemen' => $this->pindahkanGambar($desain, $data['data_desain']['elemen'], $request)],
                'versi' => $desain->versi + 1,
            ]);
        });

        return response()->json([
            'message' => 'Desain diperbarui Admin',
            'data' => $this->ringkas($desain->fresh('template'), denganData: true),
        ]);
    }

    private function tidakDitemukan(): JsonResponse
    {
        return response()->json(['message' => self::PESAN_TIDAK_DITEMUKAN], 404);
    }

    /**
     * Draft milik User lain dijawab 404 supaya keberadaannya tidak bocor.
     */
    private function milikUser(Request $request, int $id): ?Design
    {
        return Design::query()
            ->where('user_id', $request->user()->id)
            ->with('template')
            ->find($id);
    }

    /**
     * Validasi bentuk elemen, kelengkapan isi, dan batas area cetak.
     *
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?Design $desain = null): array
    {
        $aturan = [
            'data_desain' => ['required', 'array'],
            'data_desain.elemen' => ['present', 'array'],
            'data_desain.elemen.*' => ['array'],
            'data_desain.elemen.*.tipe' => ['required', 'in:teks,gambar'],
            'data_desain.elemen.*.x' => ['required', 'numeric', 'min:0'],
            'data_desain.elemen.*.y' => ['required', 'numeric', 'min:0'],
            'data_desain.elemen.*.lebar' => ['required', 'numeric', 'gt:0'],
            'data_desain.elemen.*.tinggi' => ['required', 'numeric', 'gt:0'],
            'data_desain.elemen.*.rotasi' => ['nullable', 'numeric'],
            'data_desain.elemen.*.z' => ['nullable', 'integer'],
            // FR-CDSN-02 #2: isi teks beserta font, ukuran, dan warna.
            'data_desain.elemen.*.isi' => ['nullable', 'string', 'max:500'],
            'data_desain.elemen.*.font' => ['nullable', 'string', 'max:100'],
            'data_desain.elemen.*.ukuran' => ['nullable', 'numeric', 'gt:0'],
            'data_desain.elemen.*.warna' => ['nullable', 'string', 'max:20'],
            // FR-CDSN-03: rujukan berkas gambar, bukan isi berkasnya.
            'data_desain.elemen.*.berkas' => ['nullable', 'string', 'max:255'],
            'variant_id' => ['sometimes', 'nullable', 'integer', 'exists:variants,id'],
        ];

        // Template hanya ditentukan saat draft lahir; mengubah template berarti
        // koordinat elemen lama tidak lagi bermakna (FR-CDSN-01 #2).
        if ($desain === null) {
            $aturan['template_id'] = ['required', 'integer', 'exists:templates,id'];
        }

        $data = $request->validate($aturan, [
            'template_id.required' => 'Template desain wajib dipilih',
            'template_id.exists' => 'Template desain tidak ditemukan',
            'variant_id.exists' => 'Varian tidak ditemukan',
            'data_desain.required' => 'Isi desain wajib dikirim',
            'data_desain.elemen.required' => 'Daftar elemen desain wajib dikirim',
            'data_desain.elemen.*.tipe.required' => 'Tipe elemen wajib diisi',
            'data_desain.elemen.*.tipe.in' => 'Tipe elemen harus teks atau gambar',
            'data_desain.elemen.*.x.*' => 'Posisi elemen tidak boleh negatif',
            'data_desain.elemen.*.y.*' => 'Posisi elemen tidak boleh negatif',
            'data_desain.elemen.*.lebar.*' => 'Ukuran elemen harus lebih dari 0',
            'data_desain.elemen.*.tinggi.*' => 'Ukuran elemen harus lebih dari 0',
            'data_desain.elemen.*.rotasi.numeric' => 'Rotasi elemen harus berupa angka',
            'data_desain.elemen.*.z.integer' => 'Urutan lapisan harus berupa bilangan bulat',
        ]);

        $template = $desain === null
            ? Template::find($data['template_id'])
            : $desain->template;

        if ($desain === null && ! $template->is_aktif) {
            throw ValidationException::withMessages([
                'template_id' => 'Template desain tidak tersedia',
            ]);
        }

        $this->pastikanVarianSesuai($data['variant_id'] ?? null, $template);

        $elemen = $data['data_desain']['elemen'];

        $this->pastikanIsiElemen($elemen, $template, $request, $desain);

        return $data;
    }

    private function pastikanVarianSesuai(?int $variantId, ?Template $template): void
    {
        if ($variantId === null || $template === null) {
            return;
        }

        $varian = Variant::find($variantId);

        if ($varian === null || $varian->product_id !== $template->product_id) {
            throw ValidationException::withMessages([
                'variant_id' => 'Varian tidak sesuai produk template',
            ]);
        }
    }

    /**
     * FR-CDSN-02 #2/#7 dan FR-CDSN-03 #1: setiap elemen harus punya isi sesuai
     * tipenya, gambar harus berasal dari unggahan sesi ini (atau folder desain
     * ini sendiri), dan kotak elemen harus berada di dalam area cetak.
     *
     * @param  array<int, array<string, mixed>>  $elemen
     */
    private function pastikanIsiElemen(array $elemen, ?Template $template, Request $request, ?Design $desain = null): void
    {
        $galat = [];
        $tujuan = $desain === null ? null : UnggahanDesain::folderDesain($desain->id);
        $folderSesi = UnggahanDesain::folder($request).'/';

        foreach ($elemen as $indeks => $item) {
            $awalan = 'data_desain.elemen.'.$indeks.'.';

            if ($item['tipe'] === 'teks' && trim((string) ($item['isi'] ?? '')) === '') {
                $galat[$awalan.'isi'] = 'Elemen teks wajib berisi teks';
            }

            if ($item['tipe'] === 'gambar') {
                $berkas = $item['berkas'] ?? null;

                if (! is_string($berkas) || $berkas === '') {
                    $galat[$awalan.'berkas'] = 'Elemen gambar wajib menyebut berkas unggahan';
                } elseif (! $this->berkasBolehDipakai($berkas, $folderSesi, $tujuan)) {
                    $galat[$awalan.'berkas'] = 'Gambar tidak ditemukan';
                }
            }

            if ($this->keluarArea($item, $template)) {
                $galat[$awalan.'x'] = 'Elemen harus berada di dalam area kustomisasi';
            }
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }
    }

    private function berkasBolehDipakai(string $berkas, string $folderSesi, ?string $folderDesain): bool
    {
        $disk = Storage::disk(self::DISK);

        // Gambar milik draft ini sendiri tetap boleh dipakai ulang saat diubah.
        if ($folderDesain !== null && str_starts_with($berkas, $folderDesain.'/')) {
            return $disk->exists($berkas);
        }

        return str_starts_with($berkas, $folderSesi) && $disk->exists($berkas);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function keluarArea(array $item, ?Template $template): bool
    {
        if ($template === null) {
            return false;
        }

        $kiri = $template->area_x ?? 0;
        $atas = $template->area_y ?? 0;
        $kanan = $template->area_x === null
            ? $template->lebar_kanvas
            : $template->area_x + $template->area_lebar;
        $bawah = $template->area_y === null
            ? $template->tinggi_kanvas
            : $template->area_y + $template->area_tinggi;

        return $item['x'] < $kiri
            || $item['y'] < $atas
            || $kanan < $item['x'] + $item['lebar']
            || $bawah < $item['y'] + $item['tinggi'];
    }

    /**
     * FR-CDSN-02 #10 + FR-CDSN-03 #5: berkas unggahan sementara disalin ke
     * folder desain supaya tidak hilang saat daftar sementara dibersihkan,
     * lalu berkas desain yang tidak lagi dipakai dibuang.
     *
     * @param  array<int, array<string, mixed>>  $elemen
     * @return array<int, array<string, mixed>>
     */
    private function pindahkanGambar(Design $desain, array $elemen, Request $request): array
    {
        $tujuan = UnggahanDesain::folderDesain($desain->id);
        $peta = [];

        foreach ($elemen as $indeks => $item) {
            if ($item['tipe'] !== 'gambar') {
                continue;
            }

            $berkas = $item['berkas'];

            if (! isset($peta[$berkas])) {
                if (str_starts_with($berkas, $tujuan.'/')) {
                    $peta[$berkas] = $berkas;
                } else {
                    $baru = $tujuan.'/'.basename($berkas);
                    Storage::disk(self::DISK)->copy($berkas, $baru);
                    $elemen[$indeks]['berkas'] = $baru;
                    $peta[$berkas] = $baru;

                    // Berkas sementara dilepas setelah salinannya ada.
                    Storage::disk(self::DISK)->delete($berkas);

                    continue;
                }
            }

            $elemen[$indeks]['berkas'] = $peta[$berkas];
        }

        $dipakai = array_values($peta);

        foreach (Storage::disk(self::DISK)->files($tujuan) as $lama) {
            if (! in_array($lama, $dipakai, true)) {
                Storage::disk(self::DISK)->delete($lama);
            }
        }

        return $elemen;
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkas(Design $desain, bool $denganData): array
    {
        $data = $desain->data_desain;

        if (! $denganData) {
            $data = null;
        } elseif (is_array($data) && isset($data['elemen'])) {
            foreach ($data['elemen'] as $indeks => $item) {
                if (($item['tipe'] ?? null) === 'gambar') {
                    $data['elemen'][$indeks]['url'] = GambarPublik::url($item['berkas']);
                }
            }
        }

        return [
            'id' => $desain->id,
            'status' => $desain->status,
            'versi' => $desain->versi,
            'variant_id' => $desain->variant_id,
            'catatan_revisi' => $desain->catatan_revisi,
            'batas_revisi_pada' => $desain->batas_revisi_pada?->toIso8601String(),
            'diperbarui_pada' => $desain->updated_at?->toIso8601String(),
            'template' => $desain->template === null ? null : $this->ringkasTemplate($desain->template),
            'data_desain' => $data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkasTemplate(Template $template): array
    {
        return [
            'id' => $template->id,
            'nama' => $template->nama,
            'file_url' => GambarPublik::url($template->file),
            'lebar_kanvas' => $template->lebar_kanvas,
            'tinggi_kanvas' => $template->tinggi_kanvas,
            'area_x' => $template->area_x,
            'area_y' => $template->area_y,
            'area_lebar' => $template->area_lebar,
            'area_tinggi' => $template->area_tinggi,
        ];
    }
}
