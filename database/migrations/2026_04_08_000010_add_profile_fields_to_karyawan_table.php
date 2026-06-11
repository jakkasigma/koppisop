<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table): void {
            if (! Schema::hasColumn('karyawan', 'alamat')) {
                $table->text('alamat')->nullable()->after('no_telepon');
            }

            if (! Schema::hasColumn('karyawan', 'foto_profil_path')) {
                $table->string('foto_profil_path')->nullable()->after('alamat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table): void {
            if (Schema::hasColumn('karyawan', 'foto_profil_path')) {
                $table->dropColumn('foto_profil_path');
            }

            if (Schema::hasColumn('karyawan', 'alamat')) {
                $table->dropColumn('alamat');
            }
        });
    }
};
