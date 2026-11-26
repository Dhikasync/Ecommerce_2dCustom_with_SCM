<?php

namespace App\Http\Controllers\Pembayaran;

use App\Exceptions\Pembayaran\MidtransException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Pembayaran\Midtrans;
use App\Services\Pembayaran\SinkronPembayaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FR-PAYM-01, UC-31/UC-33. Pembayaran hanya lewat Midtrans (SI-01); tidak ada
 * pembayaran manual maupun unggah bukti transfer.
 */
class PembayaranController extends Controller
{
    /** Respons memuat token sesi pembayaran: jangan disimpan perantara. */
    private const CACHE_CONTROL = 'no-store, private';

    public function __construct(
        private readonly Midtrans $midtrans,
        private readonly SinkronPembayaran $sinkron,
    ) {}

    /**
     * FR-PAYM-01 #1: menampilkan pilihan metode pembayaran dari payment gateway.
     * Skenario #3 juga bergantung di sini: setelah gagal, User boleh mencoba lagi.
     */
    public function buat(Request $request, string $nomor): JsonResponse
    {
        $pesanan = Order::query()
            ->where('nomor_pesanan', $nomor)
            ->where('user_id', $request->user()->id)
            ->first();

        // NFR-SEC-03: pesanan User lain dijawab sama seperti pesanan yang tidak ada.
        if (! $pesanan) {
            return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
        }

        $penolakan = $this->penolakan($pesanan);

        if ($penolakan !== null) {
            return response()->json(['message' => $penolakan], 422);
        }

        // Satu pesanan cukup satu tagihan aktif: membuka ulang halaman tidak
        // boleh membuat transaksi kedua di Midtrans (SI-01).
        $terbuka = $pesanan->payments()
            ->where('status', 'belum_dibayar')
            ->whereNotNull('snap_token')
            ->latest('id')
            ->first();

        if ($terbuka) {
            return $this->jawab($pesanan, $terbuka, 200);
        }

        try {
            $transaksi = $this->midtrans->buatTransaksi(
                $pesanan->nomor_pesanan,
                $pesanan->total,
                $pesanan->kadaluwarsa_pada,
                [
                    // Salinan alamat dipakai karena datanya sudah pasti terisi.
                    'nama' => $pesanan->nama_penerima,
                    'email' => $request->user()->email,
                    'telepon' => $pesanan->no_hp,
                ],
            );
        } catch (MidtransException $e) {
            report($e);

            return response()->json([
                'message' => 'Pembayaran belum dapat diproses, silakan coba lagi',
            ], 422);
        }

        // Baris dibuat setelah gateway menjawab, supaya kegagalan tidak
        // meninggalkan pembayaran tanpa token yang tidak bisa dipakai.
        $pembayaran = $pesanan->payments()->create([
            'snap_token' => $transaksi['snap_token'],
            'jumlah' => $pesanan->total,
            'status' => 'belum_dibayar',
            'kadaluwarsa_pada' => $pesanan->kadaluwarsa_pada,
        ]);

        return $this->jawab($pesanan, $pembayaran, 201, $transaksi['redirect_url']);
    }

    /**
     * FR-PAYM-01 #5, UC-33: memverifikasi langsung ke gateway saat notifikasi
     * terlambat, lalu menyamakan status pembayaran dan pesanan.
     */
    public function cek(Request $request, string $nomor): JsonResponse
    {
        $pesanan = Order::query()
            ->where('nomor_pesanan', $nomor)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $pesanan) {
            return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
        }

        $pembayaran = $pesanan->payments()->latest('id')->first();

        // Sudah lunas: keadaan lokal adalah yang terbaru, jadi tidak perlu
        // memanggil gateway lagi dan efeknya tidak mungkin dobel.
        if ($pembayaran?->status === 'lunas') {
            return $this->jawabSinkron($pesanan, $pembayaran);
        }

        // FR-PAYM-01 #5: notifikasi gateway yang tertunda bisa datang setelah
        // scheduler membatalkan pesanan. Baris `payments` menandakan percobaan
        // nyata pernah dibuat, jadi keadaan lokal tetap harus disamakan dengan
        // gateway — inilah jalur pemulihan untuk uang yang sudah masuk.
        if (! $pembayaran) {
            return response()->json([
                'message' => 'Pembayaran untuk pesanan ini belum dibuat',
            ], 422);
        }

        try {
            $status = $this->midtrans->status($pesanan->nomor_pesanan);
        } catch (MidtransException $e) {
            report($e);

            return response()->json([
                'message' => 'Pembayaran belum dapat diproses, silakan coba lagi',
            ], 422);
        }

        // Jawaban pihak ketiga tidak boleh langsung dipercaya: pastikan yang
        // ditagih memang pesanan ini, dengan nominal yang sama.
        if ($status['order_id'] !== $pesanan->nomor_pesanan) {
            report(new MidtransException('order_id Midtrans tidak cocok: '.$status['order_id']));

            return response()->json([
                'message' => 'Pembayaran belum dapat diproses, silakan coba lagi',
            ], 422);
        }

        if ($status['jumlah'] !== $pesanan->total) {
            return response()->json([
                'message' => 'Nominal pembayaran tidak cocok dengan pesanan',
            ], 422);
        }

        $this->sinkron->terapkan($pesanan, $pembayaran, $status);

        return $this->jawabSinkron($pesanan->refresh(), $pembayaran->refresh());
    }

    private function jawabSinkron(Order $pesanan, Payment $pembayaran): JsonResponse
    {
        return response()->json(['data' => [
            'nomor_pesanan' => $pesanan->nomor_pesanan,
            'status_pembayaran' => $pembayaran->status,
            'status_pesanan' => $pesanan->status,
            'dibayar_pada' => $pembayaran->dibayar_pada?->toIso8601String(),
        ]])->header('Cache-Control', self::CACHE_CONTROL);
    }

    /**
     * Pesan penolakan bukan kutipan dokumen: User Story tidak menetapkan
     * kalimatnya, hanya keadaannya (PAYM-01 #1/#3, PAYM-02 #1).
     */
    private function penolakan(Order $pesanan): ?string
    {
        // FR-PAYM-02: pesanan custom dibayar setelah desain disetujui Admin.
        if (in_array($pesanan->status, ['menunggu_validasi', 'menunggu_revisi_desain'], true)) {
            return 'Pembayaran hanya dapat dilakukan setelah desain disetujui';
        }

        if ($pesanan->status !== 'menunggu_pembayaran') {
            return $pesanan->payments()->where('status', 'lunas')->exists()
                ? 'Pesanan ini sudah dibayar'
                : 'Pesanan ini tidak dapat dibayar';
        }

        // FR-CDSN-05 #6: status order bukan bukti desain sudah Disetujui.
        $desain = $pesanan->orderItems()
            ->whereNotNull('design_id')
            ->with('design')
            ->get()
            ->pluck('design')
            ->filter();

        if ($desain->isNotEmpty() && $desain->contains(fn ($item) => $item->status !== 'disetujui')) {
            return 'Pembayaran hanya dapat dilakukan setelah desain disetujui';
        }

        // FR-PAYM-01 #4: batas bayar ditegakkan di sini dan oleh scheduler.
        if ($pesanan->kadaluwarsa_pada === null || $pesanan->kadaluwarsa_pada->isPast()) {
            return 'Batas pembayaran pesanan sudah lewat';
        }

        return null;
    }

    private function jawab(Order $pesanan, Payment $pembayaran, int $status, ?string $redirect = null): JsonResponse
    {
        return response()->json(['data' => [
            'nomor_pesanan' => $pesanan->nomor_pesanan,
            'snap_token' => $pembayaran->snap_token,
            // Hanya permintaan yang membuat transaksi baru yang punya tautan.
            'redirect_url' => $redirect,
            'jumlah' => $pembayaran->jumlah,
            'kadaluwarsa_pada' => $pembayaran->kadaluwarsa_pada?->toIso8601String(),
        ]], $status)->header('Cache-Control', self::CACHE_CONTROL);
    }
}
