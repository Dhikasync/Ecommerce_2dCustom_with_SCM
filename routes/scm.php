<?php

use App\Http\Controllers\Scm\StokController;
use Illuminate\Support\Facades\Route;

/** FR-SCM-01: stok gudang hanya untuk Admin SCM (NFR-SEC-03). */
Route::middleware(['auth', 'active', 'role:admin_scm'])->group(function () {
    Route::post('/admin/stok/varian/{id}/awal', [StokController::class, 'awal'])
        ->whereNumber('id')
        ->name('admin.stok.awal');
});
