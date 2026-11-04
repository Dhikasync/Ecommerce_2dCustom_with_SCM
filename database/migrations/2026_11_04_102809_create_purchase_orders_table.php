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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->constrained('distributors')->onDelete('restrict');
            $table->string('nomor_po')->unique();
            $table->enum('status', ['dibuat', 'dikirim', 'diproses_distributor', 'selesai', 'dibatalkan'])->default('dibuat');
            $table->integer('total_biaya');
            $table->date('estimasi_tanggal_kirim')->nullable();
            $table->text('alasan_pembatalan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
