<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * FR-CDSN-02 s.d. FR-CDSN-05: data kanvas, preview, status validasi,
     * versi desain, dan batas waktu revisi (3 hari kalender per putaran).
     * Status desain mengikuti Lampiran B: Menunggu Validasi, Perlu Revisi,
     * Disetujui, Kedaluwarsa.
     */
    public function up(): void
    {
        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // FR-PROD-01 #5: hapus produk harus tetap bisa dilakukan. Template
            // ikut terhapus bersama produknya (templates.product_id cascade),
            // jadi tautan dari desain harus dilepas, bukan menghalangi.
            // Isi desain tetap utuh di data_desain dan preview_gambar.
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('designs');
    }
};
