<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-PAYM-01/02, FR-PAYM-01 #3/#4, FR-PAYM-04 #2. Percobaan bayar boleh
     * berulang, tetapi hanya satu baris 'lunas' per pesanan (index parsial).
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('snap_token')->nullable();
            $table->string('transaction_id')->nullable()->unique();
            $table->string('metode')->nullable();
            $table->integer('jumlah');
            $table->enum('status', ['belum_dibayar', 'lunas', 'gagal', 'kedaluwarsa', 'dikembalikan'])->default('belum_dibayar');
            $table->timestamp('kadaluwarsa_pada')->nullable();
            $table->timestamp('dibayar_pada')->nullable();
            $table->timestamp('dikembalikan_pada')->nullable();
            $table->integer('jumlah_dikembalikan')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('status');
            $table->index('kadaluwarsa_pada');
        });

        DB::statement('alter table payments add constraint payments_jumlah_check check (jumlah >= 0)');
        DB::statement('alter table payments add constraint payments_jumlah_dikembalikan_check check (jumlah_dikembalikan is null or jumlah_dikembalikan >= 0)');

        // Lampiran A: satu pesanan hanya menerima satu pembayaran, jadi index
        // ini parsial pada baris 'lunas', bukan seluruh order_id.
        DB::statement("create unique index payments_lunas_unique on payments (order_id) where status = 'lunas'");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
