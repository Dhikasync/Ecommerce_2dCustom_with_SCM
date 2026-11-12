<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-CDSN-05 #5. Satu baris per pengajuan/revisi: tanggal, versi, status,
     * dan catatan revisi.
     */
    public function up(): void
    {
        Schema::create('design_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_id')->constrained('designs')->cascadeOnDelete();
            $table->integer('versi');
            $table->jsonb('data_desain')->nullable();
            $table->string('preview_gambar')->nullable();
            $table->enum('status', ['menunggu_validasi', 'perlu_revisi', 'disetujui', 'kedaluwarsa']);
            $table->text('catatan_revisi')->nullable();
            $table->foreignId('divalidasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('design_id');
            $table->index('status');
        });

        DB::statement('alter table design_revisions add constraint design_revisions_versi_check check (versi >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('design_revisions');
    }
};
