<?php

/**
 * Modul 4.4 Desain Kustom (CDSN), srs.md:461: FR-CDSN-01 kelola template desain.
 * Hanya Admin Ecommerce, ditegakkan middleware `role` + `active` (NFR-SEC-03).
 */

use App\Http\Controllers\Desain\TemplateController;
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
