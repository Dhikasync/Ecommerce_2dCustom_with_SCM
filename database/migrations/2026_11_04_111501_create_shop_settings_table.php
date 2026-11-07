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
     * FR-CKOT-04 alamat asal pengiriman toko: hanya satu baris pengaturan.
     */
    public function up(): void
    {
        Schema::create('shop_settings', function (Blueprint $table) {
            $table->id();
            $table->text('alamat_jalan');
            $table->string('kode_lokasi');
            $table->string('label_lokasi');
            $table->timestamps();
        });

        DB::statement('alter table shop_settings add constraint shop_settings_singleton_check check (id = 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_settings');
    }
};
