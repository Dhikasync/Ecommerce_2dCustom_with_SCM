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
     * FR-PAYM-01/02 pembayaran lewat payment gateway, FR-PAYM-01 #3 User dapat
     * mencoba ulang setelah Gagal, FR-PAYM-01 #4 batas bayar 24 jam,
     * FR-PAYM-04 #2 refund atas pembatalan yang disetujui.
     *
     * Satu pesanan dapat memiliki beberapa percobaan pembayaran; status
     * pembayaran pesanan mengikuti percobaan terakhir. Tidak ada bukti transfer
     * manual: pembayaran hanya lewat gateway.
     *
     * Yang dibatasi database hanyalah baris 'lunas': percobaan boleh gagal
     * berkali-kali, tetapi pembayaran yang diterima hanya satu per pesanan.
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

        // Lampiran A (Status Pembayaran): Lunas adalah pembayaran yang diterima,
        // dan satu pesanan hanya menerima pembayaran satu kali. Percobaan yang
        // gagal boleh berulang (FR-PAYM-01 #3), karena itu index ini parsial:
        // hanya baris 'lunas' yang dibatasi, bukan seluruh order_id.
        DB::statement("create unique index payments_lunas_unique on payments (order_id) where status = 'lunas'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
