<?php

namespace App\Services\Pembayaran;

use App\Mail\Pesanan\PembayaranMail;
use App\Models\Notification;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * FR-PAYM-01 #2/#4, FR-ORDR-01 #3, AS-07: kabar perubahan status dikirim lewat
 * dua kanal yang diizinkan dokumen — in-app dan email. Tanpa WhatsApp.
 */
class KabarPembayaran
{
    /**
     * Hanya dua keadaan yang wajib dikabarkan: Lunas (#2) dan kedaluwarsa (#4).
     * Kegagalan pembayaran (#3) tidak diatur, jadi tidak mengirim apa pun.
     */
    public function kirim(Order $pesanan, string $statusPembayaran): void
    {
        [$judul, $pesan] = match ($statusPembayaran) {
            'lunas' => [
                'Pembayaran diterima',
                'Pembayaran pesanan '.$pesanan->nomor_pesanan.' sudah kami terima. Pesanan sedang diproses.',
            ],
            'kedaluwarsa' => [
                'Pesanan dibatalkan',
                'Pesanan '.$pesanan->nomor_pesanan.' dibatalkan karena batas pembayaran lewat. Stok yang sempat direservasi sudah dilepas.',
            ],
            default => ['', ''],
        };

        if ($judul === '') {
            return;
        }

        Notification::create([
            'user_id' => $pesanan->user_id,
            'judul' => $judul,
            'pesan' => $pesan,
            'tipe' => 'pembayaran',
        ]);

        // SI-03/FR-SYS-01 #8: kegagalan SMTP dicatat, tidak boleh membatalkan
        // perubahan status pembayaran yang sudah sah.
        try {
            Mail::to($pesanan->user->email)->send(new PembayaranMail($pesanan, $judul, $pesan));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
