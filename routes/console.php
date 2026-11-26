<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// FR-SYS-01 #1: batas bayar 24 jam (FR-PAYM-01 #4, FR-PAYM-02 #3). Tiap menit
// karena `kadaluwarsa_pada` berpresisi detik; query memakai index kolom itu.
Schedule::command('orders:kedaluwarsa')->everyMinute()->withoutOverlapping();

// FR-SYS-01 #2 (WAJIB): masa revisi 3 hari kalender (FR-CDSN-05 #8).
Schedule::command('orders:revisi-kedaluwarsa')->everyMinute()->withoutOverlapping();
