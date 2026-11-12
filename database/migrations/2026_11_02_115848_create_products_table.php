<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** FR-PROD-01, FR-CDSN-06/07: produk jadi dan custom, SLA hanya untuk custom. */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('nama');
            $table->text('deskripsi');
            $table->enum('tipe', ['jadi', 'custom']);
            $table->integer('harga_dasar');
            $table->string('gambar')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->integer('sla_hari')->nullable();
            $table->timestamps();

            $table->index('category_id');
            $table->index('tipe');
        });

        DB::statement('alter table products add constraint products_harga_dasar_check check (harga_dasar >= 0)');
        DB::statement('alter table products add constraint products_sla_hari_check check (sla_hari is null or sla_hari > 0)');

        // FR-CDSN-07 #4: custom boleh dibuat Nonaktif dulu, tetapi tidak boleh
        // aktif tanpa SLA karena akan tampil di katalog.
        DB::statement("alter table products add constraint products_custom_aktif_check
            check (tipe = 'jadi' or not is_aktif or sla_hari is not null)");
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
