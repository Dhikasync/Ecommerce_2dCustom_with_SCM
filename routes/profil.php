<?php

/**
 * Modul 4.2 Profil dan Alamat (PROF), srs.md:365: FR-PROF-01 #4/#8/#11/#12,
 * UC-06, UC-08, FR-AUTH-06 #7. Dimuat di group `web`: session/CSRF aktif.
 */

use App\Http\Controllers\Profil\AlamatController;
use App\Http\Controllers\Profil\LokasiController;
use App\Http\Controllers\Profil\ProfilController;
use Illuminate\Support\Facades\Route;

// Pencarian dipanggil saat User mengetik; 30/menit pilihan teknis, bukan aturan
// dokumen. `auth` + `active`: UC-08 aktornya User, akun Nonaktif ditolak (FR-AUTH-01).
Route::get('/lokasi', [LokasiController::class, 'cari'])
    ->middleware(['auth', 'active', 'throttle:30,1'])
    ->name('lokasi.cari');

// FR-PROF-01 #1: Admin pun berhak melihat datanya sendiri, jadi tanpa `role:user`.
Route::get('/profil', [ProfilController::class, 'tampil'])
    ->middleware(['auth', 'active'])
    ->name('profil.tampil');

// FR-PROF-01 #2, #3, #10: ubah nama dan nomor telepon.
Route::put('/profil', [ProfilController::class, 'ubah'])
    ->middleware(['auth', 'active'])
    ->name('profil.ubah');

// FR-PROF-01 #4: alamat hanya tersimpan dengan lokasi hasil pencarian.
Route::get('/profil/alamat', [AlamatController::class, 'daftar'])
    ->middleware(['auth', 'active'])
    ->name('alamat.index');

Route::post('/profil/alamat', [AlamatController::class, 'simpan'])
    ->middleware(['auth', 'active'])
    ->name('alamat.store');

// FR-PROF-01 #5: ubah alamat tersimpan.
Route::put('/profil/alamat/{id}', [AlamatController::class, 'ubah'])
    ->middleware(['auth', 'active'])
    ->name('alamat.update');

// FR-PROF-01 #6: hapus alamat (hanya kalau tersisa lebih dari satu).
Route::delete('/profil/alamat/{id}', [AlamatController::class, 'hapus'])
    ->middleware(['auth', 'active'])
    ->name('alamat.destroy');

// FR-PROF-01 #7: tandai alamat utama.
Route::put('/profil/alamat/{id}/utama', [AlamatController::class, 'jadikanUtama'])
    ->middleware(['auth', 'active'])
    ->name('alamat.utama');

// FR-PROF-01 #9. Throttle 6/menit membatasi tebakan password lama; angka pilihan teknis.
Route::put('/profil/password', [ProfilController::class, 'ubahPassword'])
    ->middleware(['auth', 'active', 'throttle:6,1'])
    ->name('profil.password.ubah');

// FR-PROF-01 #8. Throttle 6/menit karena endpoint ini mengirim dua email.
Route::put('/profil/email', [ProfilController::class, 'ajukanEmail'])
    ->middleware(['auth', 'active', 'throttle:6,1'])
    ->name('profil.email.ajukan');

// FR-AUTH-06 #7. Tanpa `auth` karena tautan dibuka dari aplikasi email;
// keamanan dari `signed` (24 jam) + hash token.
Route::get('/profil/email/verify/{id}/{hash}', [ProfilController::class, 'konfirmasiEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('profil.email.verify');
