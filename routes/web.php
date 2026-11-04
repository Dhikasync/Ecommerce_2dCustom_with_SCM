<?php

use Illuminate\Support\Facades\Route;
use App\Models\Product;

Route::get('/products', function () {
    return response()->json(Product::with('category' , 'variants')->get());
});
