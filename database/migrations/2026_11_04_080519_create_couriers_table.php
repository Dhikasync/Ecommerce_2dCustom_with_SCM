<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * FR-CKOT-04 Kelola Kurir dan Pengaturan Ongkir.
     * Satu kode kurir dapat memiliki beberapa layanan, tetapi pasangan
     * kode + layanan tidak boleh ganda.
     */
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->string('kode');
            $table->string('nama');
            $table->string('layanan');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->unique(['kode', 'layanan']);
            $table->index('is_aktif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('couriers');
    }
};
