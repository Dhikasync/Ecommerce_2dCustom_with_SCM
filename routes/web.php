<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Halaman utama Nichibag',
    ]);
})->name('home');

// Katalog dibangun terpisah saat FR-KTLG-01: filter aktif, paginasi, harga termurah.
