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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->enum('jenis_potongan', ['nominal', 'persentase']);
            $table->integer('nilai_potongan');
            $table->integer('batas_maksimal_potongan')->nullable();
            $table->integer('minimal_belanja')->default(0);
            $table->date('tanggal_mulai');
            $table->date('tanggal_berakhir');
            $table->integer('kuota_total');
            $table->integer('batas_pemakaian_per_user');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
