<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NFR-SEC-07: aksi sensitif Admin dicatat dengan pelaku, waktu, dan
     * perubahan. Yang dicatat: persetujuan pembatalan, penyesuaian stok, dan
     * perubahan harga produk.
     *
     * data_lama dan data_baru memakai jsonb supaya bentuk perubahan tiap aksi
     * bisa berbeda tanpa menambah kolom baru.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('aksi');
            $table->string('entitas');
            $table->unsignedBigInteger('entitas_id');
            $table->jsonb('data_lama')->nullable();
            $table->jsonb('data_baru')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['entitas', 'entitas_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
