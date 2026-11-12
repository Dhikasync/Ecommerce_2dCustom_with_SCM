<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-PROD-01 #3, FR-PROD-03, FR-SCM-01, FR-SCM-02 #1. Stok Tersedia =
     * stok_fisik - stok_direservasi tidak pernah disimpan sebagai kolom.
     */
    public function up(): void
    {
        Schema::create('variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('ukuran')->nullable();
            $table->string('warna')->nullable();
            $table->string('ketebalan')->nullable();
            $table->integer('harga');
            $table->integer('berat');
            $table->integer('moq');
            $table->integer('stok_fisik')->default(0);
            $table->integer('stok_direservasi')->default(0);
            $table->integer('stok_minimum')->default(0);
            $table->timestamps();

            $table->index('product_id');
        });

        DB::statement('alter table variants add constraint variants_harga_check check (harga >= 0)');
        DB::statement('alter table variants add constraint variants_berat_check check (berat > 0)');
        DB::statement('alter table variants add constraint variants_moq_check check (moq >= 1)');
        DB::statement('alter table variants add constraint variants_stok_fisik_check check (stok_fisik >= 0)');
        DB::statement('alter table variants add constraint variants_stok_direservasi_check check (stok_direservasi >= 0)');
        DB::statement('alter table variants add constraint variants_stok_minimum_check check (stok_minimum >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('variants');
    }
};
