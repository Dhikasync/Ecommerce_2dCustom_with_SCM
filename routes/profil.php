<?php

/**
 * Modul 4.2 Profil dan Alamat (PROF) — srs.md:365
 * FR-PROF-01 #4, #8, #11/#12, UC-06, UC-08, plus FR-AUTH-06 #7.
 *
 * File ini dimuat dari bootstrap/app.php di dalam middleware group `web`,
 * jadi session dan CSRF tetap aktif seperti route di routes/auth.php.
 */

use App\Http\Controllers\LokasiController;
use App\Http\Controllers\ProfilController;
use Illuminate\Support\Facades\Route;

// Pencarian dipanggil saat User mengetik, jadi dibatasi per menit sebagai
// pengaman kuota API. Angka 30 bukan aturan dokumen, hanya pilihan teknis.
// `auth` + `active`: UC-08 aktornya User, dan akun Nonaktif tidak boleh
// memakai sesi lama (FR-AUTH-01).
Route::get('/lokasi', [LokasiController::class, 'cari'])
    ->middleware(['auth', 'active', 'throttle:30,1'])
    ->name('lokasi.cari');

// FR-PROF-01 #1: lihat profil sendiri. Tidak memakai `role:user` karena
// Admin pun berhak melihat datanya sendiri; dashboard admin tetap terpisah.
Route::get('/profil', [ProfilController::class, 'tampil'])
    ->middleware(['auth', 'active'])
    ->name('profil.tampil');

// FR-PROF-01 #2, #3, #10: ubah nama dan nomor telepon.
Route::put('/profil', [ProfilController::class, 'ubah'])
    ->middleware(['auth', 'active'])
    ->name('profil.ubah');

// FR-PROF-01 #9: ubah password dari halaman profil. Throttle 6/menit untuk
// membatasi tebakan password lama; angkanya pilihan teknis, bukan aturan
// dokumen.
Route::put('/profil/password', [ProfilController::class, 'ubahPassword'])
    ->middleware(['auth', 'active', 'throttle:6,1'])
    ->name('profil.password.ubah');

// FR-PROF-01 #8: ajukan perubahan email. Throttle 6/menit karena endpoint
// ini mengirim dua email; angkanya pilihan teknis, bukan aturan dokumen.
Route::put('/profil/email', [ProfilController::class, 'ajukanEmail'])
    ->middleware(['auth', 'active', 'throttle:6,1'])
    ->name('profil.email.ajukan');

// FR-AUTH-06 #7: konfirmasi dari tautan yang dikirim ke email baru.
// Tanpa `auth` karena tautan dibuka dari aplikasi email, belum tentu di
// browser yang punya sesi. Keamanan dari `signed` (24 jam) + hash token.
Route::get('/profil/email/verify/{id}/{hash}', [ProfilController::class, 'konfirmasiEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('profil.email.verify');
