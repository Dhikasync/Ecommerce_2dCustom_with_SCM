<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;

/**
 * FR-ORDR-01 #1/#2, Lampiran B: tiga domain status yang dipisah dokumen —
 * status pesanan, status pembayaran, status desain. Turunannya ditulis di satu
 * tempat supaya daftar, detail, dan respons checkout tidak mungkin berbeda.
 */
class RingkasanPesanan
{
    /**
     * Lampiran A: "Belum Dibayar" selama belum ada pembayaran. Baris `payments`
     * baru lahir saat transaksi gateway dimulai (FR-PAYM-01 #1).
     */
    public static function statusPembayaran(Order $pesanan): string
    {
        return $pesanan->latestPayment?->status ?? 'belum_dibayar';
    }

    /** FR-ORDR-01 #2: pesanan produk jadi tidak punya status desain. */
    public static function statusDesain(Order $pesanan): string
    {
        return $pesanan->orderItems->pluck('design')->filter()->first()?->status ?? 'tidak_ada';
    }

    /**
     * Satu baris daftar "Pesanan Saya" (FR-ORDR-01 #1, srs.md:584).
     *
     * @return array<string, mixed>
     */
    public static function untukDaftar(Order $pesanan): array
    {
        return [
            'id' => $pesanan->id,
            'nomor_pesanan' => $pesanan->nomor_pesanan,
            'status' => $pesanan->status,
            'status_pembayaran' => self::statusPembayaran($pesanan),
            'status_desain' => self::statusDesain($pesanan),
            'total' => $pesanan->total,
            'jumlah_item' => (int) $pesanan->orderItems->sum('quantity'),
            // FR-ORDR-01: nomor resi hanya terisi setelah pesanan Dikirim.
            'nomor_resi' => $pesanan->nomor_resi,
            'alasan_pembatalan' => $pesanan->alasan_pembatalan,
            'dibuat_pada' => $pesanan->created_at?->toIso8601String(),
            'kadaluwarsa_pada' => $pesanan->kadaluwarsa_pada?->toIso8601String(),
        ];
    }

    /**
     * Detail satu pesanan (FR-ORDR-01 #2, user-story.md:569). Item memakai
     * salinan nama/harga saat pesanan dibentuk (FR-PROD-01 #5), bukan harga
     * produk sekarang.
     *
     * @return array<string, mixed>
     */
    public static function untukDetail(Order $pesanan): array
    {
        return array_merge(self::untukDaftar($pesanan), [
            'subtotal_barang' => $pesanan->subtotal_barang,
            'ongkir' => $pesanan->ongkir,
            'diskon_voucher' => $pesanan->diskon_voucher,
            'item' => $pesanan->orderItems->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'nama_produk' => $item->nama_produk,
                'nama_varian' => $item->nama_varian,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'harga_satuan' => $item->harga_satuan,
                'subtotal' => $item->harga_satuan * $item->quantity,
                'is_custom' => $item->design_id !== null,
                'sla_hari' => $item->sla_hari,
                'desain' => $item->design === null ? null : [
                    'id' => $item->design->id,
                    'status' => $item->design->status,
                    'versi' => $item->design->versi,
                    'preview' => GambarPublik::url($item->design->preview_gambar),
                ],
            ])->all(),
            // Salinan alamat saat checkout: tetap terbaca walau alamat dihapus
            // (FR-CKOT-01 #2).
            'alamat' => [
                'nama_penerima' => $pesanan->nama_penerima,
                'no_hp' => $pesanan->no_hp,
                'alamat_pengiriman' => $pesanan->alamat_pengiriman,
                'label_lokasi' => $pesanan->label_lokasi,
            ],
            'kurir' => $pesanan->courier === null ? null : [
                'kode' => $pesanan->courier->kode,
                'nama' => $pesanan->courier->nama,
                'layanan' => $pesanan->courier->layanan,
            ],
            'dikirim_pada' => $pesanan->dikirim_pada?->toIso8601String(),
            // FR-ORDR-04 #3: dokumen mengizinkan tautan eksternal, bukan API tracking sendiri.
            'tautan_lacak' => $pesanan->nomor_resi === null || blank(config('services.tracking.base_url'))
                ? null
                : rtrim((string) config('services.tracking.base_url'), '/').'/'.rawurlencode($pesanan->nomor_resi),
            'selesai_pada' => $pesanan->selesai_pada?->toIso8601String(),
            'riwayat' => $pesanan->statusHistories
                ->sortBy('id')
                ->values()
                ->map(fn (OrderStatusHistory $baris) => [
                    'status_lama' => $baris->status_lama,
                    'status_baru' => $baris->status_baru,
                    // Perubahan oleh gateway tidak punya pelaku User/Admin.
                    'oleh' => $baris->changedBy?->name ?? 'Sistem',
                    'catatan' => $baris->catatan,
                    'waktu' => $baris->created_at?->toIso8601String(),
                ])->all(),
        ]);
    }
}
