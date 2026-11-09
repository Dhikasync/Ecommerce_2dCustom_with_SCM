<?php

use App\Models\Product;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Halaman utama Nichibag',
    ]);
})->name('home');

Route::get('/products', function () {
    return response()->json(Product::with('category', 'variants')->get());
});
