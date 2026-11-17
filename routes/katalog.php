<?php

/**
 * Modul 4.4 Katalog Produk (KTLG), srs.md:426: FR-KTLG-01 daftar, FR-KTLG-02 detail.
 * Publik: srs.md:430 menyatakan katalog tidak memerlukan login.
 */

use App\Http\Controllers\Katalog\KatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/katalog', [KatalogController::class, 'daftar'])
    ->name('katalog.index');

Route::get('/katalog/{id}', [KatalogController::class, 'detail'])
    ->name('katalog.show');

Route::get('/kategori', [KatalogController::class, 'kategori'])
    ->name('katalog.kategori');
