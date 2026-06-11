<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kasir_shift_sessions')) {
            return;
        }

        Schema::table('kasir_shift_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('kasir_shift_sessions', 'total_pengeluaran')) {
                $table->decimal('total_pengeluaran', 14, 2)->default(0)->after('total_debit');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('kasir_shift_sessions')) {
            return;
        }

        Schema::table('kasir_shift_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('kasir_shift_sessions', 'total_pengeluaran')) {
                $table->dropColumn('total_pengeluaran');
            }
        });
    }
};
