<?php

/**
 * Modul 4.6 Keranjang & Checkout (CKOT), srs.md:507: FR-CKOT-01 (User) dan
 * FR-CKOT-04 (Admin Ecommerce, ditegakkan middleware `role` + `active`).
 */

use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\Checkout\KurirController;
use App\Http\Controllers\Checkout\PengaturanPengirimanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])
    ->prefix('checkout')
    ->group(function () {
        // FR-CKOT-01 #2/#3/#6: ringkasan pilihan sebelum pesanan dibentuk.
        Route::get('/', [CheckoutController::class, 'tampil'])
            ->name('checkout.tampil');

        // FR-CKOT-01 #3/#8: ongkir dari berat total, asal toko, dan lokasi tujuan.
        Route::post('/ongkir', [CheckoutController::class, 'ongkir'])
            ->name('checkout.ongkir');

        // FR-CKOT-01 #1/#4/#5/#7: pesanan dibentuk dan stok direservasi.
        Route::post('/', [CheckoutController::class, 'buat'])
            ->name('checkout.buat');
    });

Route::middleware(['auth', 'active', 'role:admin_ecommerce'])
    ->prefix('admin')
    ->group(function () {
        // FR-CKOT-04 #2-#4: aktif/nonaktif layanan; tidak ada route tambah.
        Route::get('/kurir', [KurirController::class, 'daftar'])
            ->name('kurir.index');

        Route::put('/kurir/{id}/aktif', [KurirController::class, 'aktifkan'])
            ->name('kurir.aktif');

        Route::put('/kurir/{id}/nonaktif', [KurirController::class, 'nonaktifkan'])
            ->name('kurir.nonaktif');

        // FR-CKOT-04 #1: alamat asal toko sebagai titik awal ongkir.
        Route::get('/pengaturan-pengiriman', [PengaturanPengirimanController::class, 'tampil'])
            ->name('pengaturan-pengiriman.tampil');

        Route::put('/pengaturan-pengiriman', [PengaturanPengirimanController::class, 'simpan'])
            ->name('pengaturan-pengiriman.simpan');
    });
