<?php

namespace App\Http\Controllers\Checkout;

use App\Exceptions\Checkout\KeranjangBerubah;
use App\Exceptions\Checkout\OngkirTidakTersedia;
use App\Exceptions\Profil\RajaOngkirException;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Courier;
use App\Models\Design;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Produk\Variant;
use App\Models\Profil\Address;
use App\Models\ShopSetting;
use App\Services\Profil\RajaOngkir;
use App\Support\GambarPublik;
use App\Support\MasalahItemKeranjang;
use App\Support\RingkasanPesanan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FR-CKOT-01, UC-26/UC-27: mengubah item keranjang pilihan menjadi pesanan.
 * Stok baru direservasi di sini, bukan saat item masuk keranjang (srs.md:507).
 */
class CheckoutController extends Controller
{
    /** Pesan persis FR-CART-01 #4 (user-story.md:445). */
    private const PESAN_PILIH_ITEM = 'Pilih minimal satu item untuk di-checkout';

    /** Pesan persis FR-CART-01 #5 (user-story.md:446). */
    private const PESAN_CAMPUR = 'Produk jadi dan produk custom di-checkout terpisah, selesaikan salah satunya terlebih dahulu';

    /** Pesan persis FR-CKOT-01 #4 (user-story.md:460). */
    private const PESAN_ALAMAT_KURIR = 'Alamat dan kurir wajib dipilih';

    /** Pesan persis FR-CKOT-01 #8 (user-story.md:464). */
    private const PESAN_ONGKIR = 'Ongkir belum dapat dihitung, silakan coba lagi';

    private const PESAN_ITEM_TIDAK_DITEMUKAN = 'Item keranjang tidak ditemukan';

    /** Bukan aturan dokumen: batas teknis agar jumlah parameter tetap wajar. */
    private const BATAS_ITEM = 50;

    /** Respons checkout memuat alamat dan nomor telepon: jangan disimpan perantara. */
    private const CACHE_CONTROL = 'no-store, private';

    public function __construct(private readonly RajaOngkir $rajaOngkir) {}

    /**
     * Skenario #2, #3, #6: isi checkout sebelum pesanan dibentuk. Alamat utama
     * hanya dipilih otomatis kalau lokasinya sudah terisi.
     */
    public function tampil(Request $request): JsonResponse
    {
        $item = $this->itemTerpilih($request);

        if ($item instanceof JsonResponse) {
            return $item;
        }

        $alamat = $request->user()->addresses()->orderByDesc('is_utama')->orderBy('id')->get();
        $utama = $alamat->firstWhere('is_utama', true);
        $sla = $this->sla($item);

        return response()->json(['data' => [
            'bagian' => $this->bagian($item),
            'items' => $item->map(fn (CartItem $satu) => $this->ringkasItem($satu))->values()->all(),
            'subtotal_barang' => $this->subtotal($item),
            'berat_total' => $this->berat($item),
            'alamat' => [
                'dipilih' => $utama?->kode_lokasi !== null ? $utama->id : null,
                'daftar' => $alamat->map(fn (Address $satu) => $this->ringkasAlamat($satu))->values()->all(),
            ],
            'kurir' => $this->kurirAktif(),
            'sla_hari' => $sla,
            'estimasi_sla' => $this->estimasiSla($sla),
        ]])->header('Cache-Control', self::CACHE_CONTROL);
    }

    /** Skenario #3 dan #8: ongkir dari berat total, lalu total = subtotal + ongkir. */
    public function ongkir(Request $request): JsonResponse
    {
        $pilihan = $this->pilihan($request);

        if ($pilihan instanceof JsonResponse) {
            return $pilihan;
        }

        try {
            $ongkir = $this->biayaOngkir($pilihan['item'], $pilihan['alamat'], $pilihan['kurir']);
        } catch (OngkirTidakTersedia) {
            return $this->ongkirTidakTersedia();
        }

        $subtotal = $this->subtotal($pilihan['item']);

        return response()->json(['data' => [
            'subtotal_barang' => $subtotal,
            'berat_total' => $this->berat($pilihan['item']),
            'ongkir' => $ongkir,
            'total' => $subtotal + $ongkir,
        ]])->header('Cache-Control', self::CACHE_CONTROL);
    }

    /**
     * Skenario #1, #4, #5, #7: pesanan dibentuk beserta reservasi stok. Ongkir
     * dihitung ulang di server, bukan diambil dari body permintaan.
     */
    public function buat(Request $request): JsonResponse
    {
        $pilihan = $this->pilihan($request);

        if ($pilihan instanceof JsonResponse) {
            return $pilihan;
        }

        $item = $pilihan['item'];

        $bermasalah = $this->itemBermasalah($item);

        if ($bermasalah !== []) {
            return $this->keranjangBerubah($bermasalah);
        }

        try {
            $ongkir = $this->biayaOngkir($item, $pilihan['alamat'], $pilihan['kurir']);
        } catch (OngkirTidakTersedia) {
            return $this->ongkirTidakTersedia();
        }

        try {
            $pesanan = DB::transaction(fn () => $this->simpan(
                $request, $item, $pilihan['alamat'], $pilihan['kurir'], $ongkir,
            ));
        } catch (KeranjangBerubah $e) {
            return $this->keranjangBerubah($e->items);
        }

        return response()->json(['data' => $this->ringkasPesanan($pesanan)], 201)
            ->header('Cache-Control', self::CACHE_CONTROL);
    }

    /**
     * @param  Collection<int, CartItem>  $item
     */
    private function simpan(Request $request, Collection $item, Address $alamat, Courier $kurir, int $ongkir): Order
    {
        // NFR-PRF-05: angka stok diperiksa ulang di bawah kunci, bukan dari bacaan
        // sebelum transaksi, supaya dua checkout bersamaan tidak menjual stok sama.
        $varian = Variant::query()
            ->whereIn('id', $item->pluck('variant_id')->all())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $bermasalah = [];

        foreach ($item as $satu) {
            $stok = $varian[$satu->variant_id];

            if ($satu->quantity > $stok->stok_fisik - $stok->stok_direservasi) {
                $bermasalah[] = ['id' => $satu->id, 'label' => MasalahItemKeranjang::STOK];
            }
        }

        if ($bermasalah !== []) {
            throw new KeranjangBerubah($bermasalah);
        }

        $custom = $this->bagian($item) === 'custom';
        $subtotal = $this->subtotal($item);

        $pesanan = Order::create([
            'user_id' => $request->user()->id,
            'address_id' => $alamat->id,
            'courier_id' => $kurir->id,
            'nomor_pesanan' => 'INV-'.strtoupper((string) Str::ulid()),
            'status' => $custom ? 'menunggu_validasi' : 'menunggu_pembayaran',
            'subtotal_barang' => $subtotal,
            'ongkir' => $ongkir,
            'diskon_voucher' => 0,
            'total' => $subtotal + $ongkir,
            // Skenario #2: alamat disalin, karena alamat boleh dihapus (FR-PROF-01 #6).
            'nama_penerima' => $alamat->nama_penerima,
            'no_hp' => $alamat->no_hp,
            'alamat_pengiriman' => $alamat->alamat_lengkap,
            'label_lokasi' => $alamat->label_lokasi,
            // Batas bayar 24 jam hanya untuk produk jadi (Lampiran A, srs.md:822).
            'kadaluwarsa_pada' => $custom ? null : now()->addDay(),
        ]);

        foreach ($item as $satu) {
            $produk = $satu->variant->product;

            $pesanan->orderItems()->create([
                'variant_id' => $satu->variant_id,
                'design_id' => $satu->design_id,
                'nama_produk' => $produk->nama,
                'nama_varian' => $this->namaVarian($satu->variant),
                'sku' => $satu->variant->sku,
                'quantity' => $satu->quantity,
                // Harga yang sudah dikunci saat item masuk keranjang (FR-CART-01 #6).
                'harga_satuan' => $satu->harga_satuan,
                // FR-CDSN-07 #3: SLA disalin supaya perubahan produk tidak mengubahnya.
                'sla_hari' => $custom ? $produk->sla_hari : null,
            ]);

            $varian[$satu->variant_id]->increment('stok_direservasi', $satu->quantity);

            if ($satu->design_id !== null) {
                Design::whereKey($satu->design_id)->update(['status' => 'menunggu_validasi']);
            }
        }

        // FR-ORDR-01 #2: status awal ikut tercatat di riwayat.
        OrderStatusHistory::create([
            'order_id' => $pesanan->id,
            'status_lama' => null,
            'status_baru' => $pesanan->status,
            'diubah_oleh' => $request->user()->id,
        ]);

        // Item yang sudah dipesan keluar dari keranjang; bagian lain tetap tinggal.
        CartItem::whereIn('id', $item->pluck('id')->all())->delete();

        return $pesanan;
    }

    /**
     * Skenario #2/#3: alamat dan kurir harus dipilih dan masih bisa dipakai.
     * Alasan yang tidak bisa dipakai dijawab dengan kalimat #4 yang sama, tanpa
     * membedakan milik User lain (NFR-SEC-03).
     *
     * @return array{item: Collection<int, CartItem>, alamat: Address, kurir: Courier}|JsonResponse
     */
    private function pilihan(Request $request): array|JsonResponse
    {
        if (! is_numeric($request->input('address_id')) || ! is_numeric($request->input('courier_id'))) {
            return response()->json(['message' => self::PESAN_ALAMAT_KURIR], 422);
        }

        $item = $this->itemTerpilih($request);

        if ($item instanceof JsonResponse) {
            return $item;
        }

        $alamat = $request->user()->addresses()->whereKey((int) $request->input('address_id'))->first();

        // Skenario #2: alamat tanpa lokasi tidak dapat dipakai untuk pengiriman.
        if ($alamat === null || $alamat->kode_lokasi === null) {
            return response()->json(['message' => self::PESAN_ALAMAT_KURIR], 422);
        }

        $kurir = Courier::whereKey((int) $request->input('courier_id'))->where('is_aktif', true)->first();

        if ($kurir === null) {
            return response()->json(['message' => self::PESAN_ALAMAT_KURIR], 422);
        }

        return ['item' => $item, 'alamat' => $alamat, 'kurir' => $kurir];
    }

    /**
     * FR-CART-01 #4/#5: pilihan berasal dari satu bagian dan milik User sendiri.
     *
     * @return Collection<int, CartItem>|JsonResponse
     */
    private function itemTerpilih(Request $request): Collection|JsonResponse
    {
        $masukan = $request->input('item_ids');

        // Pilihan kosong adalah skenario #4; bentuk id yang tidak dikenal bukan
        // "kosong", jadi dijawab seperti id yang tidak ada (NFR-SEC-03).
        if (! is_array($masukan) || $masukan === []) {
            return response()->json(['message' => self::PESAN_PILIH_ITEM], 422);
        }

        $id = [];

        foreach ($masukan as $satu) {
            if (is_int($satu)) {
                $id[] = $satu;

                continue;
            }

            // `is_numeric` meloloskan "1e3"/"1.5"/"1e999", dan PostgreSQL menolak
            // bind itu sebagai bigint — jalan gratis menuju 500.
            if (is_string($satu) && ctype_digit($satu)) {
                $id[] = (int) $satu;

                continue;
            }

            return response()->json(['message' => self::PESAN_ITEM_TIDAK_DITEMUKAN], 404);
        }

        $id = array_values(array_unique($id));

        // Batas 50 menjaga jumlah parameter di bawah batas PostgreSQL (65.535);
        // tanpa itu 66.000 id = 2,6 detik query lalu 500.
        if (count($id) > self::BATAS_ITEM) {
            return response()->json(['message' => self::PESAN_ITEM_TIDAK_DITEMUKAN], 404);
        }

        $item = CartItem::query()
            ->whereIn('id', $id)
            ->whereHas('cart', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('variant.product')
            ->orderBy('id')
            ->get();

        if ($item->count() !== count($id)) {
            return response()->json(['message' => self::PESAN_ITEM_TIDAK_DITEMUKAN], 404);
        }

        $jadi = $item->whereNull('design_id')->count();

        if ($jadi !== 0 && $jadi !== $item->count()) {
            return response()->json(['message' => self::PESAN_CAMPUR], 422);
        }

        return $item;
    }

    /**
     * @param  Collection<int, CartItem>  $item
     */
    private function bagian(Collection $item): string
    {
        return $item->first()->design_id === null ? 'jadi' : 'custom';
    }

    /**
     * @param  Collection<int, CartItem>  $item
     */
    private function subtotal(Collection $item): int
    {
        return (int) $item->sum(fn (CartItem $satu) => $satu->quantity * $satu->harga_satuan);
    }

    /**
     * Berat dasar ongkir: jumlah berat varian kali quantity, dalam gram.
     *
     * @param  Collection<int, CartItem>  $item
     */
    private function berat(Collection $item): int
    {
        return (int) $item->sum(fn (CartItem $satu) => $satu->variant->berat * $satu->quantity);
    }

    /**
     * FR-CDSN-06: satu pesanan memakai SLA terpanjang di antara itemnya, karena
     * produksi selesai setelah item yang paling lama ikut selesai.
     *
     * @param  Collection<int, CartItem>  $item
     */
    private function sla(Collection $item): ?int
    {
        return $item->map(fn (CartItem $satu) => $satu->variant->product->sla_hari)->filter()->max();
    }

    private function estimasiSla(?int $sla): ?string
    {
        if ($sla === null) {
            return null;
        }

        return "Estimasi {$sla} hari kerja setelah status desain Disetujui DAN status pembayaran Lunas";
    }

    /**
     * @param  Collection<int, CartItem>  $item
     * @return list<array{id: int, label: string}>
     */
    private function itemBermasalah(Collection $item): array
    {
        return $item
            ->map(fn (CartItem $satu) => ['id' => $satu->id, 'label' => MasalahItemKeranjang::label($satu)])
            ->filter(fn (array $satu) => $satu['label'] !== null)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, CartItem>  $item
     *
     * @throws OngkirTidakTersedia
     */
    private function biayaOngkir(Collection $item, Address $alamat, Courier $kurir): int
    {
        // FR-CKOT-04 #1: titik awal ongkir adalah alamat asal toko.
        $asal = ShopSetting::find(1)?->kode_lokasi;

        // Tanpa alamat asal, sistem tidak bisa menghitung: bukan Rp 0 (srs.md:520).
        if (! is_string($asal) || $asal === '') {
            throw new OngkirTidakTersedia('Alamat asal toko belum diisi');
        }

        try {
            $tarif = $this->rajaOngkir->hitungOngkir(
                $asal, (string) $alamat->kode_lokasi, $this->berat($item), $kurir->kode,
            );
        } catch (RajaOngkirException $e) {
            throw new OngkirTidakTersedia($e->getMessage(), previous: $e);
        }

        // Skenario #8 menyebut "tidak mengembalikan tarif" sebagai gangguan yang sama.
        $baris = collect($tarif)->firstWhere('layanan', $kurir->layanan);

        if ($baris === null) {
            throw new OngkirTidakTersedia("Layanan {$kurir->layanan} tidak dikembalikan RajaOngkir");
        }

        return (int) $baris['biaya'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function kurirAktif(): array
    {
        return Courier::query()
            ->where('is_aktif', true)
            ->orderBy('kode')
            ->orderBy('layanan')
            ->get()
            ->map(fn (Courier $kurir) => [
                'id' => $kurir->id,
                'kode' => $kurir->kode,
                'nama' => $kurir->nama,
                'layanan' => $kurir->layanan,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkasAlamat(Address $alamat): array
    {
        return [
            'id' => $alamat->id,
            'nama_penerima' => $alamat->nama_penerima,
            'no_hp' => $alamat->no_hp,
            'alamat_lengkap' => $alamat->alamat_lengkap,
            'kode_lokasi' => $alamat->kode_lokasi,
            'label_lokasi' => $alamat->label_lokasi,
            'is_utama' => $alamat->is_utama,
            'bisa_dipakai' => $alamat->kode_lokasi !== null,
            // FR-PROF-01 #5 lewat skenario #2: alamat lama ditandai "Lengkapi lokasi".
            'catatan' => $alamat->kode_lokasi === null ? 'Lengkapi lokasi' : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkasItem(CartItem $item): array
    {
        $varian = $item->variant;

        return [
            'id' => $item->id,
            'variant_id' => $item->variant_id,
            'design_id' => $item->design_id,
            'nama' => $varian->product->nama,
            'gambar_url' => GambarPublik::url($varian->product->gambar),
            'sku' => $varian->sku,
            'ukuran' => $varian->ukuran,
            'warna' => $varian->warna,
            'ketebalan' => $varian->ketebalan,
            'quantity' => $item->quantity,
            'harga_satuan' => $item->harga_satuan,
            'subtotal' => $item->quantity * $item->harga_satuan,
            'label' => MasalahItemKeranjang::label($item),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkasPesanan(Order $pesanan): array
    {
        $pesanan->load('orderItems.design', 'latestPayment');

        $sla = $pesanan->orderItems->pluck('sla_hari')->filter()->max();

        return [
            'id' => $pesanan->id,
            'nomor_pesanan' => $pesanan->nomor_pesanan,
            'status' => $pesanan->status,
            'status_pembayaran' => RingkasanPesanan::statusPembayaran($pesanan),
            'status_desain' => RingkasanPesanan::statusDesain($pesanan),
            'subtotal_barang' => $pesanan->subtotal_barang,
            'ongkir' => $pesanan->ongkir,
            'diskon_voucher' => $pesanan->diskon_voucher,
            'total' => $pesanan->total,
            'kadaluwarsa_pada' => $pesanan->kadaluwarsa_pada,
            'sla_hari' => $sla,
            'estimasi_sla' => $this->estimasiSla($sla),
            'items' => $pesanan->orderItems
                ->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'nama' => $item->nama_produk,
                    'nama_varian' => $item->nama_varian,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'harga_satuan' => $item->harga_satuan,
                    'subtotal' => $item->quantity * $item->harga_satuan,
                    'design_id' => $item->design_id,
                    'sla_hari' => $item->sla_hari,
                ])
                ->values()
                ->all(),
        ];
    }

    private function namaVarian(Variant $varian): ?string
    {
        $bagian = array_filter([$varian->ukuran, $varian->warna, $varian->ketebalan]);

        return $bagian === [] ? null : implode(' / ', $bagian);
    }

    /**
     * @param  list<array{id: int, label: string}>  $items
     */
    private function keranjangBerubah(array $items): JsonResponse
    {
        return response()->json([
            'message' => 'Keranjang berubah, periksa kembali item Anda',
            'data' => ['items' => $items],
        ], 422);
    }

    private function ongkirTidakTersedia(): JsonResponse
    {
        return response()->json(['message' => self::PESAN_ONGKIR], 422);
    }
}
