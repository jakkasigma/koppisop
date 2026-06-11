<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            if (! Schema::hasColumn('karyawan', 'is_active')) {
                $table->boolean('is_active')->default(true)->index('karyawan_is_active_index');
            }
        });

        if (Schema::hasColumn('karyawan', 'is_active')) {
            DB::table('karyawan')->whereNull('is_active')->update(['is_active' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            if (Schema::hasColumn('karyawan', 'is_active')) {
                $table->dropIndex('karyawan_is_active_index');
                $table->dropColumn('is_active');
            }
        });
    }
};

