<?php

/**
 * Modul 4.3 Produk (PROD) - srs.md:398
 * FR-PROD-02 (kelola kategori) dan FR-PROD-01 (kelola produk + gambar).
 * FR-PROD-03 (MOQ per varian) menyusul di file yang sama.
 *
 * srs.md:398: hanya Admin Ecommerce yang mengelola produk, kategori, dan MOQ.
 * Pembatasan itu ditegakkan di sisi server (NFR-SEC-03) lewat middleware
 * `role`, dan `active` menolak akun yang dinonaktifkan walau masih memegang
 * sesi lama (FR-AUTH-01).
 *
 * File ini dimuat dari bootstrap/app.php di dalam middleware group `web`,
 * jadi session dan CSRF tetap aktif seperti routes/auth.php dan
 * routes/profil.php.
 */

use App\Http\Controllers\Produk\KategoriController;
use App\Http\Controllers\Produk\ProductController;
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
        // Unggah gambar memakai multipart/form-data; Laravel membaca
        // `_method=PUT` saat form HTML mengirim ubah produk.
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
    });
