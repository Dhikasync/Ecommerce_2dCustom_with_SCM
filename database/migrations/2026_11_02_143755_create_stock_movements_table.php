<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** FR-SCM-01 #4-#6: penyesuaian stok, kartu stok, dan input stok awal. */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('variants')->cascadeOnDelete();
            $table->enum('tipe', ['stok_awal', 'penjualan', 'restock', 'pembatalan', 'penyesuaian']);
            $table->integer('jumlah_masuk')->default(0);
            $table->integer('jumlah_keluar')->default(0);
            $table->integer('stok_fisik_setelah');
            $table->integer('stok_direservasi_setelah');
            $table->string('alasan')->nullable();
            $table->string('referensi')->nullable();
            $table->timestamps();

            $table->index('variant_id');
            $table->index('tipe');
        });

        DB::statement('alter table stock_movements add constraint stock_movements_jumlah_check check (jumlah_masuk >= 0 and jumlah_keluar >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
