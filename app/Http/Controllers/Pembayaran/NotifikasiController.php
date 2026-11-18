<?php

namespace App\Http\Controllers\Pembayaran;

use App\Exceptions\Pembayaran\MidtransException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Pembayaran\JumlahMidtrans;
use App\Services\Pembayaran\KabarPembayaran;
use App\Services\Pembayaran\Midtrans;
use App\Services\Pembayaran\SinkronPembayaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FR-PAYM-01 #2/#3/#4, UC-31, KM-02. Webhook Midtrans: satu-satunya jalur di
 * mana gateway boleh mengubah status pesanan tanpa tindakan User.
 */
class NotifikasiController extends Controller
{
    public function __construct(
        private readonly Midtrans $midtrans,
        private readonly SinkronPembayaran $sinkron,
        private readonly KabarPembayaran $kabar,
    ) {}

    public function terima(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'string'],
            'status_code' => ['required', 'string'],
            'gross_amount' => ['required', 'string'],
            'signature_key' => ['required', 'string'],
            'transaction_status' => ['required', 'string'],
            'transaction_id' => ['nullable', 'string'],
            'payment_type' => ['nullable', 'string'],
            'fraud_status' => ['nullable', 'string'],
        ]);

        // KM-02: keaslian dibuktikan sebelum satu baris pun berubah.
        if (! $this->midtrans->tandaTanganSah($data)) {
            report(new MidtransException('Tanda tangan notifikasi Midtrans tidak sah'));

            return response()->json(['message' => 'Tanda tangan notifikasi tidak sah'], 403);
        }

        $pesanan = Order::where('nomor_pesanan', $data['order_id'])->first();

        if (! $pesanan) {
            return response()->json(['message' => 'Pesanan tidak ditemukan'], 404);
        }

        $pembayaran = $this->pembayaran($pesanan, $data['transaction_id'] ?? null);

        if (! $pembayaran) {
            return response()->json([
                'message' => 'Pembayaran untuk pesanan ini belum dibuat',
            ], 422);
        }

        try {
            $jumlah = JumlahMidtrans::baca($data['gross_amount']);
        } catch (MidtransException $e) {
            report($e);

            return response()->json(['message' => 'Nominal pembayaran tidak dikenali'], 422);
        }

        if ($jumlah !== $pesanan->total) {
            return response()->json([
                'message' => 'Nominal pembayaran tidak cocok dengan pesanan',
            ], 422);
        }

        $hasil = $this->sinkron->terapkan($pesanan, $pembayaran, [
            'order_id' => $data['order_id'],
            'transaction_id' => $data['transaction_id'] ?? null,
            'metode' => $data['payment_type'] ?? null,
            'jumlah' => $jumlah,
            'transaction_status' => $data['transaction_status'],
            'fraud_status' => $data['fraud_status'] ?? null,
        ]);

        // Kabar hanya dikirim saat status benar-benar berubah, sehingga
        // notifikasi ganda dari Midtrans tidak menghasilkan email ganda.
        if ($hasil['diterapkan']) {
            $this->kabar->kirim($pesanan->refresh(), $hasil['status_pembayaran']);
        }

        return response()->json([
            'message' => 'Notifikasi diterima',
            'data' => [
                'status_pembayaran' => $hasil['status_pembayaran'],
                'status_pesanan' => $pesanan->refresh()->status,
            ],
        ]);
    }

    /** Notifikasi dikirim per transaksi; baris lama tetap dipakai bila transaksinya sama. */
    private function pembayaran(Order $pesanan, ?string $transactionId): ?Payment
    {
        if ($transactionId !== null) {
            $cocok = $pesanan->payments()->where('transaction_id', $transactionId)->first();

            if ($cocok) {
                return $cocok;
            }
        }

        return $pesanan->payments()->latest('id')->first();
    }
}
