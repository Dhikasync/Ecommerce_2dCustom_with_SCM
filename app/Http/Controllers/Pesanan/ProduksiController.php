<?php

namespace App\Http\Controllers\Pesanan;

use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Pesanan\KabarStatusPesanan;
use App\Support\GambarPublik;
use App\Support\RingkasanPesanan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FR-ORDR-03 #5/#7, UC-38: Admin Ecommerce memulai produksi cetak untuk
 * pesanan custom yang sudah Diproses (desain Disetujui dan pembayaran Lunas).
 */
class ProduksiController extends Controller
{
    private const PESAN_TIDAK_VALID = 'Perubahan status tidak valid';

    private const PESAN_WORK_ORDER = 'Work order hanya tersedia untuk pesanan custom yang Diproses';

    public function __construct(private readonly KabarStatusPesanan $kabar) {}

    /** FR-ORDR-03 #1: daftar pesanan yang perlu diproses Admin Ecommerce. */
    public function daftar(): JsonResponse
    {
        $daftar = Order::query()
            ->whereIn('status', ['diproses', 'sedang_dicetak', 'siap_kirim'])
            ->with(['orderItems.design', 'latestPayment'])
            ->orderBy('id')
            ->get();

        $data = $daftar->map(function (Order $pesanan) {
            $dibayar = $pesanan->latestPayment?->dibayar_pada;
            $sla = $pesanan->orderItems->pluck('sla_hari')->filter()->max();
            $estimasi = $dibayar === null || $sla === null
                ? null
                : $dibayar->copy()->addWeekdays((int) $sla)->toIso8601String();

            return [
                'id' => $pesanan->id,
                'nomor_pesanan' => $pesanan->nomor_pesanan,
                'status' => $pesanan->status,
                'status_pembayaran' => RingkasanPesanan::statusPembayaran($pesanan),
                'status_desain' => RingkasanPesanan::statusDesain($pesanan),
                'alamat' => [
                    'nama_penerima' => $pesanan->nama_penerima,
                    'no_hp' => $pesanan->no_hp,
                    'alamat_pengiriman' => $pesanan->alamat_pengiriman,
                    'label_lokasi' => $pesanan->label_lokasi,
                ],
                'item' => $pesanan->orderItems->map(fn ($item) => [
                    'nama_produk' => $item->nama_produk,
                    'nama_varian' => $item->nama_varian,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                    'harga_satuan' => $item->harga_satuan,
                    'subtotal' => $item->quantity * $item->harga_satuan,
                    'preview' => $item->design?->preview_gambar === null
                        ? null
                        : GambarPublik::url($item->design->preview_gambar),
                    'sla_hari' => $item->sla_hari,
                ]),
                'sla_hari' => $sla,
                'estimasi_selesai' => $estimasi,
                'dibuat_pada' => $pesanan->created_at?->toIso8601String(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /** FR-ORDR-03 #2: spesifikasi, render final, quantity, dan tenggat SLA. */
    public function workOrder(Request $request, string $nomor): JsonResponse
    {
        $pesanan = Order::query()
            ->where('nomor_pesanan', $nomor)
            ->with(['orderItems.design', 'orderItems.variant', 'latestPayment'])
            ->first();

        if ($pesanan === null) {
            return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
        }

        $item = $pesanan->orderItems->first(fn ($baris) => $baris->design_id !== null);

        if ($pesanan->status !== 'diproses' || $item === null || $item->design?->status !== 'disetujui') {
            return response()->json(['message' => self::PESAN_WORK_ORDER], 422);
        }

        $preview = $item->design->preview_gambar;
        $dibayar = $pesanan->latestPayment?->dibayar_pada;
        $estimasi = $dibayar === null
            ? null
            : $dibayar->copy()->addWeekdays((int) $item->sla_hari);

        return response()->json(['data' => [
            'nomor_pesanan' => $pesanan->nomor_pesanan,
            'status' => $pesanan->status,
            'item' => [
                'nama_produk' => $item->nama_produk,
                'nama_varian' => $item->nama_varian,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'preview' => $preview === null ? null : GambarPublik::url($preview),
                'sla_hari' => $item->sla_hari,
            ],
            'estimasi_selesai' => $estimasi?->toIso8601String(),
        ]]);
    }

    /** FR-ORDR-03 #6: custom dari Sedang Dicetak, produk jadi dari Diproses. */
    public function siapKirim(Request $request, string $nomor): JsonResponse
    {
        $pesanan = Order::query()
            ->where('nomor_pesanan', $nomor)
            ->first();

        if ($pesanan === null) {
            return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
        }

        $custom = $pesanan->orderItems()->whereNotNull('design_id')->exists();
        $dari = $custom ? 'sedang_dicetak' : 'diproses';

        if ($pesanan->status !== $dari) {
            return response()->json(['message' => self::PESAN_TIDAK_VALID], 422);
        }

        if ($custom) {
            $designIds = $pesanan->orderItems()->whereNotNull('design_id')->pluck('design_id');
            $disetujui = Design::query()
                ->whereIn('id', $designIds)
                ->where('status', 'disetujui')
                ->count();

            if ($disetujui !== $designIds->count()) {
                return response()->json(['message' => self::PESAN_TIDAK_VALID], 422);
            }
        }

        $berubah = DB::transaction(function () use ($pesanan, $request, $dari) {
            $segar = Order::whereKey($pesanan->id)->lockForUpdate()->first();

            if ($segar === null || $segar->status !== $dari) {
                return false;
            }

            OrderStatusHistory::create([
                'order_id' => $segar->id,
                'status_lama' => $dari,
                'status_baru' => 'siap_kirim',
                'diubah_oleh' => $request->user()->id,
            ]);

            $segar->update(['status' => 'siap_kirim']);

            return true;
        });

        if (! $berubah) {
            return response()->json(['message' => self::PESAN_TIDAK_VALID], 422);
        }

        $segar = $pesanan->refresh();

        $this->kabar->kirim(
            $segar,
            'Pesanan siap kirim',
            'Pesanan '.$segar->nomor_pesanan.' sudah siap dikirim.',
        );

        return response()->json([
            'message' => 'Pesanan siap kirim',
            'data' => [
                'nomor_pesanan' => $segar->nomor_pesanan,
                'status' => $segar->status,
            ],
        ]);
    }

    public function mulaiCetak(Request $request, string $nomor): JsonResponse
    {
        $pesanan = Order::query()
            ->where('nomor_pesanan', $nomor)
            ->first();

        if ($pesanan === null) {
            return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
        }

        $designIds = $pesanan->orderItems()->whereNotNull('design_id')->pluck('design_id');

        $disetujui = Design::query()
            ->whereIn('id', $designIds)
            ->where('status', 'disetujui')
            ->count();

        if ($pesanan->status !== 'diproses' || $designIds->isEmpty() || $disetujui !== $designIds->count()) {
            return response()->json(['message' => self::PESAN_TIDAK_VALID], 422);
        }

        $berubah = DB::transaction(function () use ($pesanan, $request) {
            // FR-SYS-01 #7: baca ulang di bawah kunci sebelum mengubah status.
            $segar = Order::whereKey($pesanan->id)->lockForUpdate()->first();

            if ($segar === null || $segar->status !== 'diproses') {
                return false;
            }

            OrderStatusHistory::create([
                'order_id' => $segar->id,
                'status_lama' => 'diproses',
                'status_baru' => 'sedang_dicetak',
                'diubah_oleh' => $request->user()->id,
            ]);

            $segar->update(['status' => 'sedang_dicetak']);

            return true;
        });

        if (! $berubah) {
            return response()->json(['message' => self::PESAN_TIDAK_VALID], 422);
        }

        $segar = $pesanan->refresh();

        $this->kabar->kirim(
            $segar,
            'Pesanan sedang dicetak',
            'Pesanan '.$segar->nomor_pesanan.' memasuki tahap Sedang Dicetak.',
        );

        return response()->json([
            'message' => 'Pesanan mulai dicetak',
            'data' => [
                'nomor_pesanan' => $segar->nomor_pesanan,
                'status' => $segar->status,
            ],
        ]);
    }

    /** FR-ORDR-04 #1/#2: Admin menginput nomor resi untuk pesanan Siap Kirim. */
    public function kirim(Request $request, string $nomor): JsonResponse
    {
        $pesanan = Order::query()
            ->where('nomor_pesanan', $nomor)
            ->first();

        if ($pesanan === null) {
            return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
        }

        if ($pesanan->status !== 'siap_kirim') {
            return response()->json(['message' => self::PESAN_TIDAK_VALID], 422);
        }

        $data = $request->validate([
            'nomor_resi' => ['required', 'string', 'max:255'],
        ], [
            'nomor_resi.required' => 'Nomor resi wajib diisi',
        ]);

        $resi = trim($data['nomor_resi']);

        if ($resi === '') {
            return response()->json([
                'message' => 'Nomor resi wajib diisi',
                'errors' => ['nomor_resi' => ['Nomor resi wajib diisi']],
            ], 422);
        }

        $berubah = DB::transaction(function () use ($pesanan, $request, $resi) {
            $segar = Order::whereKey($pesanan->id)->lockForUpdate()->first();

            if ($segar === null || $segar->status !== 'siap_kirim') {
                return false;
            }

            OrderStatusHistory::create([
                'order_id' => $segar->id,
                'status_lama' => 'siap_kirim',
                'status_baru' => 'dikirim',
                'diubah_oleh' => $request->user()->id,
            ]);

            $segar->update([
                'status' => 'dikirim',
                'nomor_resi' => $resi,
                'dikirim_pada' => now(),
            ]);

            return true;
        });

        if (! $berubah) {
            return response()->json(['message' => self::PESAN_TIDAK_VALID], 422);
        }

        $segar = $pesanan->refresh();
        $tautan = route('pesanan.show', $segar->nomor_pesanan);

        $this->kabar->kirim(
            $segar,
            'Pesanan dikirim',
            'Pesanan '.$segar->nomor_pesanan.' dikirim dengan nomor resi '.$resi.'. Lacak: '.$tautan,
        );

        return response()->json([
            'message' => 'Pesanan dikirim',
            'data' => [
                'nomor_pesanan' => $segar->nomor_pesanan,
                'status' => $segar->status,
                'nomor_resi' => $segar->nomor_resi,
            ],
        ]);
    }
}
