<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jadwal_karyawan')) {
            return;
        }

        Schema::table('jadwal_karyawan', function (Blueprint $table): void {
            if (Schema::hasColumn('jadwal_karyawan', 'tanggal') && Schema::hasColumn('jadwal_karyawan', 'id_karyawan')) {
                try {
                    $table->dropUnique('jadwal_tanggal_karyawan_unique');
                } catch (\Throwable $e) {
                    // Ignore if index does not exist.
                }
            }
        });

        Schema::table('jadwal_karyawan', function (Blueprint $table): void {
            $table->unique(['tanggal', 'id_karyawan', 'shift_ke'], 'jadwal_tanggal_karyawan_shift_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('jadwal_karyawan')) {
            return;
        }

        Schema::table('jadwal_karyawan', function (Blueprint $table): void {
            try {
                $table->dropUnique('jadwal_tanggal_karyawan_shift_unique');
            } catch (\Throwable $e) {
                // Ignore if index does not exist.
            }
        });

        Schema::table('jadwal_karyawan', function (Blueprint $table): void {
            $table->unique(['tanggal', 'id_karyawan'], 'jadwal_tanggal_karyawan_unique');
        });
    }
};
