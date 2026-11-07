<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * FR-SCM-04 #2: data distributor hanyalah buku kontak yang diisi Admin SCM.
     * Distributor bukan aktor dan tidak memiliki akun atau akses sistem
     * (SRS 1.4, AS-06). Kolomnya hanya yang disebut dokumen: nama toko dan
     * nomor WhatsApp. Daftar varian yang biasa disuplai ada di tabel
     * distributor_variants.
     */
    public function up(): void
    {
        Schema::create('distributors', function (Blueprint $table) {
            $table->id();
            $table->string('nama_toko');
            $table->string('no_hp');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distributors');
    }
};
