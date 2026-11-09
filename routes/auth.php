<?php

/**
 * Modul 4.1 Autentikasi dan Akun (AUTH) — srs.md:325
 * FR-AUTH-01/02/03/04/06.
 *
 * File ini dimuat dari bootstrap/app.php di dalam middleware group `web`,
 * jadi session dan CSRF tetap aktif seperti route di routes/web.php.
 */

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

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

Route::get('/login', [AuthController::class, 'showLogin'])
    ->middleware('guest')
    ->name('login');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('guest')
    ->name('login.store');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware(['guest', 'throttle:6,1'])
    ->name('password.email');

Route::get('/reset-password/{token}', [AuthController::class, 'showReset'])
    ->middleware('guest')
    ->name('password.reset');

Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('guest')
    ->name('password.store');
