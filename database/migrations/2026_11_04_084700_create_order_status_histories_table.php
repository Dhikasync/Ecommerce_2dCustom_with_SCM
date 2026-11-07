<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * FR-ORDR-01 #2 riwayat status pesanan dan FR-ORDR-03 #5 waktu perubahan
     * status tercatat di riwayat status.
     */
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->enum('status_lama', [
                'menunggu_validasi',
                'menunggu_revisi_desain',
                'menunggu_pembayaran',
                'diproses',
                'sedang_dicetak',
                'siap_kirim',
                'dikirim',
                'selesai',
                'dibatalkan',
            ])->nullable();
            $table->enum('status_baru', [
                'menunggu_validasi',
                'menunggu_revisi_desain',
                'menunggu_pembayaran',
                'diproses',
                'sedang_dicetak',
                'siap_kirim',
                'dikirim',
                'selesai',
                'dibatalkan',
            ]);
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
