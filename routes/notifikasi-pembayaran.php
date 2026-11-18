<?php

use App\Http\Controllers\Pembayaran\NotifikasiController;
use Illuminate\Support\Facades\Route;

/*
 * FR-PAYM-01 #2/#3/#4, KM-02. Webhook dipanggil server Midtrans, bukan browser:
 * tidak ada sesi, tidak ada CSRF, dan keasliannya dibuktikan `signature_key`.
 */

Route::post('/pembayaran/notifikasi', [NotifikasiController::class, 'terima'])
    ->middleware('throttle:60,1,pembayaran-notifikasi')
    ->name('pembayaran.notifikasi');
