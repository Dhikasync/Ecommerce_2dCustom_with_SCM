<?php

use App\Http\Controllers\Pembayaran\PembayaranController;
use Illuminate\Support\Facades\Route;

/*
 * FR-PAYM-01: pembayaran hanya lewat payment gateway (SI-01).
 */

Route::middleware(['auth', 'active'])->group(function () {
    // FR-PAYM-01 #1, UC-31: buat/reuse transaksi Snap untuk pesanan produk jadi.
    // Tiap permintaan bisa memanggil Midtrans, jadi dibatasi per User.
    Route::post('/pesanan/{nomor}/pembayaran', [PembayaranController::class, 'buat'])
        ->middleware('throttle:10,1,pembayaran-buat')
        ->name('pembayaran.buat');
});
