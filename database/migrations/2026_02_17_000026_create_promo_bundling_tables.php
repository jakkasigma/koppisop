<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promo_bundling')) {
            Schema::create('promo_bundling', function (Blueprint $table): void {
                $table->id('id_promo_bundling');
                $table->string('nama_promo', 100);
                $table->decimal('harga_bundle', 12, 2);
                $table->boolean('status_aktif')->default(true);
                $table->date('tanggal_mulai')->nullable();
                $table->date('tanggal_selesai')->nullable();
                $table->string('keterangan', 255)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('promo_bundling_item')) {
            Schema::create('promo_bundling_item', function (Blueprint $table): void {
                $table->id('id_promo_bundling_item');
                $table->unsignedBigInteger('id_promo_bundling');
                $table->unsignedInteger('id_produk');
                $table->integer('qty')->default(1);
                $table->timestamps();

                $table->foreign('id_promo_bundling')->references('id_promo_bundling')->on('promo_bundling')->cascadeOnDelete();
                $table->foreign('id_produk')->references('id_produk')->on('produk')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_bundling_item');
        Schema::dropIfExists('promo_bundling');
    }
};
