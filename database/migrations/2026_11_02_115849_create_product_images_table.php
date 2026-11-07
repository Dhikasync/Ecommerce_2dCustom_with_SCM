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
     * FR-KTLG-02 #1 dan SRS 4.4.2: halaman detail produk menampilkan galeri
     * gambar, sehingga satu produk dapat memiliki banyak foto. `products.gambar`
     * tetap dipakai sebagai foto utama pada kartu katalog.
     */
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('gambar');
            $table->integer('urutan')->default(0);
            $table->timestamps();

            // Galeri selalu dibaca per produk dan berurutan.
            $table->index(['product_id', 'urutan']);
        });

        DB::statement('alter table product_images add constraint product_images_urutan_check check (urutan >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
