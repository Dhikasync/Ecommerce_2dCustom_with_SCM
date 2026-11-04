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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('address_id')->constrained('addresses')->onDelete('restrict');
            $table->foreignId('courier_id')->constrained('couriers')->onDelete('restrict');
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->onDelete('restrict');
            $table->string('nomor_pesanan')->unique();
            $table->enum('status', ['menunggu_validasi', 'menunggu_revisi_desain', 'menunggu_pembayaran', 'diproses', 'sedang_dicetak', 'siap_kirim', 'dikirim', 'selesai', 'dibatalkan']);
            $table->integer('subtotal_barang');
            $table->integer('ongkir');
            $table->integer('diskon_voucher')->default(0);
            $table->integer('total');
            $table->text('alasan_pembatalan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
