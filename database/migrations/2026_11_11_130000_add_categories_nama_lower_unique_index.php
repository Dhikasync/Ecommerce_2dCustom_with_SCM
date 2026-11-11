<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FR-PROD-02 hanya menulis "nama kategori unik" tanpa menyebut huruf besar
 * atau kecil (srs.md:415). Unique bawaan kolom `nama` di PostgreSQL peka
 * huruf, sehingga `Tas` dan `tas` bisa hidup berdampingan.
 *
 * Indeks ekspresi ini menegakkan tafsiran "unik tanpa membedakan huruf" di
 * level database, bukan hanya di validasi controller. Kolom dan tabelnya
 * tidak berubah; yang ditambah hanya satu indeks.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('create unique index categories_nama_lower_unique on categories (lower(nama))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index if exists categories_nama_lower_unique');
    }
};
