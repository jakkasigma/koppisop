<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('karyawan')) {
            return;
        }

        Schema::create('karyawan', function (Blueprint $table) {
            $table->id('id_karyawan');
            $table->string('nama_karyawan', 100)->nullable();
            $table->string('jabatan', 50)->nullable();
            $table->string('no_telepon', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawan');
    }
};
