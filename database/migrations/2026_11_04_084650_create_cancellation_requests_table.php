<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-ORDR-02 #2: User mengajukan setelah Lunas. FR-ORDR-06: Admin
     * memutuskan. Status mengikuti Lampiran A.
     */
    public function up(): void
    {
        Schema::create('cancellation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('alasan');
            $table->enum('status', ['menunggu_persetujuan', 'disetujui', 'ditolak'])->default('menunggu_persetujuan');

            // Keputusan Admin (FR-ORDR-06 #2 dan #3).
            $table->boolean('stok_dikembalikan')->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
        });

        // FR-ORDR-06 #6: parsial, karena permohonan yang sudah diputuskan tetap
        // tersimpan sebagai riwayat.
        DB::statement(
            'create unique index cancellation_requests_pending_unique '
            .'on cancellation_requests (order_id) where status = \'menunggu_persetujuan\''
        );

        // FR-ORDR-02 #2: alasan pengajuan wajib diisi.
        DB::statement(
            'alter table cancellation_requests add constraint cancellation_requests_alasan_check '
            .'check (btrim(alasan) <> \'\')'
        );

        // FR-ORDR-06 #2: menyetujui wajib memilih perlakuan stok.
        DB::statement(
            'alter table cancellation_requests add constraint cancellation_requests_keputusan_stok_check '
            .'check (status <> \'disetujui\' or stok_dikembalikan is not null)'
        );

        // FR-ORDR-06 #3 dan #4: menolak wajib menyertakan alasan.
        DB::statement(
            'alter table cancellation_requests add constraint cancellation_requests_alasan_penolakan_check '
            .'check (status <> \'ditolak\' or (alasan_penolakan is not null and btrim(alasan_penolakan) <> \'\'))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_requests');
    }
};
