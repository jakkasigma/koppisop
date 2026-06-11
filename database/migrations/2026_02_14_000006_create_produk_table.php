<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('produk')) {
            return;
        }

        Schema::create('produk', function (Blueprint $table) {
            $table->id('id_produk');
            $table->string('nama_produk', 100)->nullable();
            $table->decimal('harga', 10, 2)->nullable();
            $table->unsignedBigInteger('id_kategori')->nullable();
            $table->string('deskripsi', 255)->nullable();
            $table->integer('stok')->nullable();

            $table->foreign('id_kategori')->references('id_kategori')->on('kategori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produk');
    }
};
