<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            if (! Schema::hasColumn('karyawan', 'pin_digest')) {
                $table->char('pin_digest', 64)->nullable()->unique('karyawan_pin_digest_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            if (Schema::hasColumn('karyawan', 'pin_digest')) {
                $table->dropUnique('karyawan_pin_digest_unique');
                $table->dropColumn('pin_digest');
            }
        });
    }
};

