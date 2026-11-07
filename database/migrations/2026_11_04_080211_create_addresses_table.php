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
     * FR-PROF-01 #4/#5: alamat hanya boleh disimpan dengan lokasi terpilih
     * (kode lokasi menghitung ongkir, label lokasi ditampilkan lengkap).
     * FR-PROF-01 #7: hanya satu alamat utama per User.
     */
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nama_penerima');
            $table->string('no_hp');
            $table->text('alamat_lengkap');
            $table->string('kode_lokasi')->nullable();
            $table->string('label_lokasi')->nullable();
            $table->boolean('is_utama')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });

        DB::statement('create unique index addresses_utama_unique on addresses (user_id) where is_utama');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
