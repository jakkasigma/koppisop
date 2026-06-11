<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pesanan')) {
            return;
        }

        Schema::create('pesanan', function (Blueprint $table) {
            $table->id('id_pesanan');
            $table->unsignedBigInteger('id_pelanggan')->nullable();
            $table->unsignedBigInteger('id_karyawan')->nullable();
            $table->decimal('total_harga', 10, 2)->nullable();
            $table->dateTime('waktu_pembayaran')->nullable();
            $table->string('metode_pembayaran', 50)->nullable();
            $table->string('status_pembayaran', 20)->nullable();

            $table->foreign('id_pelanggan')->references('id_pelanggan')->on('pelanggan');
            $table->foreign('id_karyawan')->references('id_karyawan')->on('karyawan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesanan');
    }
};
