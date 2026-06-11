<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('karyawan')) {
            return;
        }

        Schema::table('karyawan', function (Blueprint $table): void {
            if (! Schema::hasColumn('karyawan', 'monthly_salary')) {
                $table->unsignedBigInteger('monthly_salary')->nullable();
            }

            if (! Schema::hasColumn('karyawan', 'hourly_rate')) {
                $table->unsignedBigInteger('hourly_rate')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('karyawan')) {
            return;
        }

        Schema::table('karyawan', function (Blueprint $table): void {
            $drops = [];
            if (Schema::hasColumn('karyawan', 'monthly_salary')) {
                $drops[] = 'monthly_salary';
            }
            if (Schema::hasColumn('karyawan', 'hourly_rate')) {
                $drops[] = 'hourly_rate';
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
