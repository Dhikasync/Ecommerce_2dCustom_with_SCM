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
     * FR-CKOT-01 Proses Checkout, FR-PAYM-01/02 batas bayar 24 jam,
     * FR-ORDR-01 s.d. FR-ORDR-04 status, resi, dan pengiriman,
     * FR-SYS-01 proses otomatis berbasis waktu.
     *
     * Alamat pengiriman disalin ke pesanan supaya FR-PROF-01 #6 (User menghapus
     * alamat) tetap berjalan tanpa merusak riwayat pesanan.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('couriers')->restrictOnDelete();
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->restrictOnDelete();
            $table->string('nomor_pesanan')->unique();
            $table->enum('status', [
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
            $table->integer('subtotal_barang');
            $table->integer('ongkir');
            $table->integer('diskon_voucher')->default(0);
            $table->integer('total');

            // Salinan alamat saat checkout (FR-CKOT-01 #2).
            $table->string('nama_penerima');
            $table->string('no_hp');
            $table->text('alamat_pengiriman');
            $table->string('label_lokasi')->nullable();

            $table->string('nomor_resi')->nullable();
            $table->text('alasan_pembatalan')->nullable();
            $table->timestamp('kadaluwarsa_pada')->nullable();
            $table->timestamp('dikirim_pada')->nullable();
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('kadaluwarsa_pada');
            $table->index('dikirim_pada');
            $table->index('voucher_id');
        });

        DB::statement('alter table orders add constraint orders_total_check check (subtotal_barang >= 0 and ongkir >= 0 and diskon_voucher >= 0 and total >= 0)');
        DB::statement('alter table orders add constraint orders_nomor_resi_check check (nomor_resi is null or nomor_resi <> \'\')');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
