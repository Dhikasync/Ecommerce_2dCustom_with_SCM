<?php

use App\Http\Controllers\Pesanan\PesananController;
use App\Http\Controllers\Pesanan\ProduksiController;
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

    Route::post('/pesanan/{nomor}/terima', [PesananController::class, 'terima'])
        ->name('pesanan.terima');
});

// FR-ORDR-03 #5/#6/#7 + FR-ORDR-04 #1/#2: aksi Admin Ecommerce.
Route::middleware(['auth', 'active', 'role:admin_ecommerce'])->group(function () {
    Route::get('/admin/pesanan/{nomor}/work-order', [ProduksiController::class, 'workOrder'])
        ->name('admin.pesanan.work-order');

    Route::post('/admin/pesanan/{nomor}/mulai-cetak', [ProduksiController::class, 'mulaiCetak'])
        ->name('admin.pesanan.mulai-cetak');

    Route::post('/admin/pesanan/{nomor}/siap-kirim', [ProduksiController::class, 'siapKirim'])
        ->name('admin.pesanan.siap-kirim');

    Route::post('/admin/pesanan/{nomor}/kirim', [ProduksiController::class, 'kirim'])
        ->name('admin.pesanan.kirim');
});
