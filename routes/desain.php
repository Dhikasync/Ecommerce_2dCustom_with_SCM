<?php

/**
 * Modul 4.4 Desain Kustom (CDSN), srs.md:461.
 * - FR-CDSN-01 kelola template desain: hanya Admin Ecommerce, ditegakkan
 *   middleware `role` + `active` (NFR-SEC-03).
 * - FR-CDSN-03 unggah gambar User: terbuka untuk Guest karena editor boleh
 *   dibuka tanpa login (user-story.md:834 hanya membatasi keranjang, checkout,
 *   dan pesanan). Berkas dipisah per token sesi.
 */

use App\Http\Controllers\Desain\TemplateController;
use App\Http\Controllers\Desain\UnggahanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'role:admin_ecommerce'])
    ->prefix('admin')
    ->group(function () {
        // FR-CDSN-01: daftar, tambah, ubah, hapus template desain.
        Route::get('/template', [TemplateController::class, 'daftar'])
            ->name('template.index');

        Route::post('/template', [TemplateController::class, 'simpan'])
            ->name('template.store');

        Route::put('/template/{id}', [TemplateController::class, 'ubah'])
            ->name('template.update');

        Route::delete('/template/{id}', [TemplateController::class, 'hapus'])
            ->name('template.destroy');
    });

// FR-CDSN-03: daftar sementara, unggah, dan hapus gambar editor.
Route::get('/desain/unggahan', [UnggahanController::class, 'daftar'])
    ->name('unggahan.index');

Route::post('/desain/unggahan', [UnggahanController::class, 'simpan'])
    ->name('unggahan.store');

Route::delete('/desain/unggahan/{nama}', [UnggahanController::class, 'hapus'])
    ->name('unggahan.destroy');
