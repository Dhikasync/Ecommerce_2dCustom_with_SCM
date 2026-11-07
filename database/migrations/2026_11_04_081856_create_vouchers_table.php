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
     * FR-CKOT-02 Kupon/Voucher Diskon dan FR-CKOT-03 #3 validasi isian voucher:
     * persentase maksimal 100, nominal harus lebih dari 0, periode harus valid.
     * Pemakaian per User dihitung dari orders.voucher_id, bukan disimpan ganda.
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

            $table->index('is_aktif');
        });

        DB::statement('alter table vouchers add constraint vouchers_nilai_check check (nilai_potongan > 0)');
        DB::statement("alter table vouchers add constraint vouchers_persentase_check check (jenis_potongan <> 'persentase' or nilai_potongan <= 100)");
        DB::statement('alter table vouchers add constraint vouchers_batas_maksimal_check check (batas_maksimal_potongan is null or batas_maksimal_potongan >= 0)');
        DB::statement('alter table vouchers add constraint vouchers_minimal_belanja_check check (minimal_belanja >= 0)');
        DB::statement('alter table vouchers add constraint vouchers_kuota_check check (kuota_total >= 0 and batas_pemakaian_per_user >= 1)');
        DB::statement('alter table vouchers add constraint vouchers_periode_check check (tanggal_berakhir >= tanggal_mulai)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
