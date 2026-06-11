<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('detail_pesanan')) {
            return;
        }

        Schema::create('detail_pesanan', function (Blueprint $table) {
            $table->unsignedBigInteger('id_pesanan');
            $table->unsignedBigInteger('id_produk');
            $table->integer('jumlah')->nullable();
            $table->decimal('harga_satuan', 10, 2)->nullable();

            $table->primary(['id_pesanan', 'id_produk']);
            $table->foreign('id_pesanan')->references('id_pesanan')->on('pesanan');
            $table->foreign('id_produk')->references('id_produk')->on('produk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_pesanan');
    }
};
