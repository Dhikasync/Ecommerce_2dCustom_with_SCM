<?php

use App\Http\Controllers\Pesanan\PesananController;
use Illuminate\Support\Facades\Route;

/*
 * FR-ORDR-01, srs.md:581: User melacak status dan riwayat pesanannya. Admin
 * tidak memakai route ini; antrian Admin ada di FR-ORDR-03.
 */

Route::middleware(['auth', 'active'])->group(function () {
    // FR-ORDR-01 #1: "Pesanan Saya" dengan filter status pesanan.
    Route::get('/pesanan', [PesananController::class, 'index'])
        ->name('pesanan.index');

    // FR-ORDR-01 #2: detail terpisah per pesanan.
    Route::get('/pesanan/{nomor}', [PesananController::class, 'show'])
        ->name('pesanan.show');
});
