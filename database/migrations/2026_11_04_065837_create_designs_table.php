<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('template_id')->constrained('templates')->onDelete('restrict');
            $table->foreignId('variant_id')->constrained('variants')->onDelete('cascade');
            $table->json('data_desain')->nullable();
            $table->string('preview_gambar')->nullable();
            $table->enum('status', ['draft', 'menunggu_validasi', 'disetujui', 'perlu_revisi'])->default('draft');
            $table->text('catatan_revisi')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('designs');
    }
};
