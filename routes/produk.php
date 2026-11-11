<?php

/**
 * Modul 4.3 Produk (PROD) - srs.md:398
 * Saat ini baru FR-PROD-02 (kelola kategori). FR-PROD-01 (produk) dan
 * FR-PROD-03 (MOQ) menyusul di file yang sama.
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
    });
