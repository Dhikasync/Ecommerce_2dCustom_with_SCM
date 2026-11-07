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
     * FR-SCM-03 Restock Barang (Purchase Order): PO berisi item, total biaya,
     * tanggal, status, dan alasan pembatalan.
     * FR-SCM-04 #3 catatan komunikasi disimpan sebagai bagian histori PO.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->constrained('distributors')->restrictOnDelete();
            $table->string('nomor_po')->unique();
            $table->enum('status', ['dibuat', 'dikirim', 'diproses_distributor', 'selesai', 'dibatalkan'])->default('dibuat');
            $table->integer('total_biaya');
            $table->date('estimasi_tanggal_kirim')->nullable();
            $table->text('catatan')->nullable();
            $table->text('alasan_pembatalan')->nullable();
            $table->timestamps();

            $table->index('distributor_id');
            $table->index('status');
        });

        DB::statement('alter table purchase_orders add constraint purchase_orders_total_biaya_check check (total_biaya >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
