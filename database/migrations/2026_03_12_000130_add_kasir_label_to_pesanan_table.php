<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pesanan')) {
            return;
        }

        Schema::table('pesanan', function (Blueprint $table): void {
            if (! Schema::hasColumn('pesanan', 'kasir_label')) {
                $table->string('kasir_label', 60)->nullable()->after('id_karyawan');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pesanan')) {
            return;
        }

        Schema::table('pesanan', function (Blueprint $table): void {
            if (Schema::hasColumn('pesanan', 'kasir_label')) {
                $table->dropColumn('kasir_label');
            }
        });
    }
};
