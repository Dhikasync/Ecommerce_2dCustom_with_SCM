<?php

use Illuminate\Support\Facades\Route;
use App\Models\Product;

Route::get('/', function () {
    return response()->json([
        'message' => 'Halaman utama Nichibag',
    ]);
})->name('home');

Route::get('/products', function () {
    return response()->json(Product::with('category' , 'variants')->get());
});
