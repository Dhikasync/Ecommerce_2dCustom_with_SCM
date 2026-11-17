<?php

/**
 * Modul 4.6 Keranjang (CART), srs.md:503: FR-CART-01, UC-24. Dimuat di group
 * `web`. Keranjang hanya untuk User yang login (srs.md:430).
 */

use App\Http\Controllers\Keranjang\KeranjangController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])
    ->prefix('keranjang')
    ->group(function () {
        // FR-CART-01 #1/#5: daftar item, dua bagian jadi dan custom.
        Route::get('/', [KeranjangController::class, 'tampil'])
            ->name('keranjang.index');

        Route::post('/item', [KeranjangController::class, 'tambah'])
            ->name('keranjang.item.store');

        Route::patch('/item/{id}', [KeranjangController::class, 'ubah'])
            ->name('keranjang.item.update');

        Route::delete('/item/{id}', [KeranjangController::class, 'hapus'])
            ->name('keranjang.item.destroy');
    });
