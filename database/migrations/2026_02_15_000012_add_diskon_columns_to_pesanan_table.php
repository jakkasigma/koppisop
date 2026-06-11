<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesanan', function (Blueprint $table) {
            $table->unsignedBigInteger('id_diskon')->nullable()->after('id_karyawan');
            $table->decimal('subtotal_harga', 12, 2)->nullable()->after('total_harga');
            $table->decimal('diskon_nominal', 12, 2)->nullable()->default(0)->after('subtotal_harga');
            $table->string('diskon_nama', 100)->nullable()->after('diskon_nominal');
            $table->string('diskon_tipe', 20)->nullable()->after('diskon_nama');
            $table->decimal('diskon_nilai', 12, 2)->nullable()->after('diskon_tipe');

            $table->foreign('id_diskon')->references('id_diskon')->on('diskon')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pesanan', function (Blueprint $table) {
            $table->dropForeign(['id_diskon']);
            $table->dropColumn([
                'id_diskon',
                'subtotal_harga',
                'diskon_nominal',
                'diskon_nama',
                'diskon_tipe',
                'diskon_nilai',
            ]);
        });
    }
};
