<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Halaman utama Nichibag',
    ]);
})->name('home');

// Katalog produk tidak lagi memakai route sementara di sini. Endpoint
// katalog dibangun terpisah saat FR-KTLG-01, dengan filter produk aktif,
// paginasi, dan harga dari varian termurah.
