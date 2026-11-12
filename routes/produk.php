<?php

/**
 * Modul 4.3 Produk (PROD), srs.md:398: FR-PROD-01/02, FR-PROD-03 #1.
 * Hanya Admin Ecommerce, ditegakkan middleware `role` + `active` (NFR-SEC-03).
 */

use App\Http\Controllers\Produk\KategoriController;
use App\Http\Controllers\Produk\ProductController;
use App\Http\Controllers\Produk\VariantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'role:admin_ecommerce'])
    ->prefix('admin')
    ->group(function () {
        // FR-PROD-02: daftar, tambah, ubah, hapus kategori.
        Route::get('/kategori', [KategoriController::class, 'daftar'])
            ->name('kategori.index');

        Route::post('/kategori', [KategoriController::class, 'simpan'])
            ->name('kategori.store');

        Route::put('/kategori/{id}', [KategoriController::class, 'ubah'])
            ->name('kategori.update');

        Route::delete('/kategori/{id}', [KategoriController::class, 'hapus'])
            ->name('kategori.destroy');

        // FR-PROD-01: daftar, tambah, ubah, hapus produk.
        // Unggah gambar memakai multipart; form HTML mengirim `_method=PUT`.
        Route::get('/produk', [ProductController::class, 'daftar'])
            ->name('produk.index');

        Route::post('/produk', [ProductController::class, 'simpan'])
            ->name('produk.store');

        Route::put('/produk/{id}', [ProductController::class, 'ubah'])
            ->name('produk.update');

        Route::delete('/produk/{id}', [ProductController::class, 'hapus'])
            ->name('produk.destroy');

        // Hapus satu foto galeri. Foto utama diganti lewat ubah produk.
        Route::delete('/produk/{id}/gambar/{gambarId}', [ProductController::class, 'hapusGambar'])
            ->name('produk.gambar.destroy');

        // FR-PROD-01 #3/#8, FR-PROD-03 #1. Varian selalu di bawah produknya agar
        // id varian milik produk lain tidak bisa disentuh dari produk ini.
        Route::get('/produk/{id}/varian', [VariantController::class, 'daftar'])
            ->name('varian.index');

        Route::post('/produk/{id}/varian', [VariantController::class, 'simpan'])
            ->name('varian.store');

        Route::put('/produk/{id}/varian/{varianId}', [VariantController::class, 'ubah'])
            ->name('varian.update');

        Route::delete('/produk/{id}/varian/{varianId}', [VariantController::class, 'hapus'])
            ->name('varian.destroy');
    });
