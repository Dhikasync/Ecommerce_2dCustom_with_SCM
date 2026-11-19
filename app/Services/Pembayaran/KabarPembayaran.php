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
     * FR-ORDR-01 #3: setiap perubahan status pembayaran dikabarkan lewat dua
     * kanal dokumen — in-app dan email. `pending` tidak mengubah apa pun,
     * sehingga tidak perlu dikabarkan.
     */
    public function kirim(Order $pesanan, string $statusPembayaran): void
    {
        [$judul, $pesan] = match ($statusPembayaran) {
            'lunas' => ['Pembayaran diterima', $this->pesanLunas($pesanan)],
            'gagal' => ['Pembayaran gagal', $this->pesanGagal($pesanan)],
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

        // SI-03: kegagalan SMTP dicatat, tidak boleh membatalkan
        // perubahan status pembayaran yang sudah sah.
        try {
            Mail::to($pesanan->user->email)->send(new PembayaranMail($pesanan, $judul, $pesan));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * FR-PAYM-01 #2 menetapkan efek Lunas adalah status Diproses. Pesanan yang
     * sudah dibatalkan sebelum notifikasinya sampai tidak berubah status, jadi
     * pesannya tidak boleh menjanjikan produksi.
     */
    private function pesanLunas(Order $pesanan): string
    {
        if ($pesanan->status === 'dibatalkan') {
            return 'Pembayaran pesanan '.$pesanan->nomor_pesanan.' sudah kami terima, tetapi pesanan tersebut sudah dibatalkan karena melewati batas waktu. Silakan hubungi Admin agar pembayaran ini dapat ditindaklanjuti.';
        }

        return 'Pembayaran pesanan '.$pesanan->nomor_pesanan.' sudah kami terima. Pesanan sedang diproses.';
    }

    /**
     * FR-PAYM-01 #3, FR-ORDR-01 #3: percobaan yang ditolak gateway tidak
     * membatalkan pesanan, jadi kabar ini menegaskan pesanan masih hidup dan
     * menyebut batas waktunya dalam WIB (NFR-LOC-01).
     */
    private function pesanGagal(Order $pesanan): string
    {
        $awal = 'Pembayaran pesanan '.$pesanan->nomor_pesanan.' belum berhasil. ';

        // Pesanan custom belum punya batas bayar sebelum desain disetujui
        // (FR-PAYM-02), jadi jangan menjanjikan tanggal yang tidak ada.
        $batas = $pesanan->kadaluwarsa_pada?->copy()->timezone('Asia/Jakarta');

        if ($batas === null) {
            return $awal.'Silakan coba lagi dengan metode pembayaran lain.';
        }

        return $awal.'Pesanan masih menunggu pembayaran sampai '
            .$batas->translatedFormat('j F Y, H.i').' WIB. '
            .'Silakan coba lagi dengan metode pembayaran lain.';
    }
}
