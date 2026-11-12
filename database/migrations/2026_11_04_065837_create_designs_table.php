<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-CDSN-02 s.d. FR-CDSN-05: kanvas, preview, versi, batas revisi 3 hari.
     * Status mengikuti Lampiran B.
     */
    public function up(): void
    {
        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // FR-PROD-01 #5: templates ikut terhapus bersama produk, jadi tautan
            // dari desain dilepas (nullOnDelete); isi desain tetap utuh.
            $table->foreignId('template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('variants')->nullOnDelete();
            $table->jsonb('data_desain')->nullable();
            $table->string('preview_gambar')->nullable();
            $table->enum('status', ['draft', 'menunggu_validasi', 'perlu_revisi', 'disetujui', 'kedaluwarsa'])->default('draft');
            $table->text('catatan_revisi')->nullable();
            $table->integer('versi')->default(1);
            $table->timestamp('batas_revisi_pada')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('template_id');
            $table->index('variant_id');
            $table->index('status');
        });

        DB::statement('alter table designs add constraint designs_versi_check check (versi >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('designs');
    }
};
