<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NFR-SEC-07: aksi sensitif Admin (pembatalan, stok, harga) dicatat dengan
     * pelaku dan perubahan. jsonb dipakai karena bentuk tiap aksi berbeda.
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

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
