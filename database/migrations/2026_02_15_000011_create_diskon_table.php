<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diskon', function (Blueprint $table) {
            $table->id('id_diskon');
            $table->string('nama_diskon', 100);
            $table->enum('tipe_diskon', ['persen', 'nominal']);
            $table->decimal('nilai_diskon', 12, 2);
            $table->decimal('minimal_belanja', 12, 2)->default(0);
            $table->boolean('status_aktif')->default(true);
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('keterangan', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diskon');
    }
};
