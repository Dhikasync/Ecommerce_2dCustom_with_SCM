<?php

/**
 * Modul 4.4 Katalog Produk (KTLG), srs.md:426: FR-KTLG-01 daftar produk.
 * Publik: srs.md:430 menyatakan katalog tidak memerlukan login.
 */

use App\Http\Controllers\Katalog\KatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/katalog', [KatalogController::class, 'daftar'])
    ->name('katalog.index');
