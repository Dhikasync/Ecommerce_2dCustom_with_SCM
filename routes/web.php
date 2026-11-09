<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Models\Product;

Route::get('/', function () {
    return response()->json([
        'message' => 'Halaman utama Nichibag',
    ]);
})->name('home');

Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/register', [AuthController::class, 'register'])
    ->middleware(['guest', 'throttle:6,1'])
    ->name('register.store');

Route::get('/email/verify', [AuthController::class, 'notice'])
    ->name('verification.notice');

Route::post('/email/verification-notification', [AuthController::class, 'resend'])
    ->name('verification.send');

Route::get('/products', function () {
    return response()->json(Product::with('category' , 'variants')->get());
});
