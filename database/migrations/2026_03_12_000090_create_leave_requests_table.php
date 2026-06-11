<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('id_karyawan');
            $table->string('jenis', 20);
            $table->date('tanggal_awal');
            $table->date('tanggal_akhir');
            $table->text('alasan')->nullable();
            $table->string('bukti_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['id_karyawan', 'status']);
            $table->index(['tanggal_awal', 'tanggal_akhir']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
