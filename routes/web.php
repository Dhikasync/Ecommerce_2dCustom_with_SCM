<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Halaman utama Nichibag',
    ]);
})->name('home');
