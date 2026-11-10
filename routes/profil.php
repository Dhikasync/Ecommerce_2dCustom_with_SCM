<?php

/**
 * Modul 4.2 Profil dan Alamat (PROF) — srs.md:365
 * FR-PROF-01 #4 (pencarian lokasi), FR-PROF-01 #11/#12, UC-08.
 *
 * File ini dimuat dari bootstrap/app.php di dalam middleware group `web`,
 * jadi session dan CSRF tetap aktif seperti route di routes/auth.php.
 */

use App\Http\Controllers\LokasiController;
use Illuminate\Support\Facades\Route;

// Pencarian dipanggil saat User mengetik, jadi dibatasi per menit sebagai
// pengaman kuota API. Angka 30 bukan aturan dokumen, hanya pilihan teknis.
// `auth` + `active`: UC-08 aktornya User, dan akun Nonaktif tidak boleh
// memakai sesi lama (FR-AUTH-01).
Route::get('/lokasi', [LokasiController::class, 'cari'])
    ->middleware(['auth', 'active', 'throttle:30,1'])
    ->name('lokasi.cari');
