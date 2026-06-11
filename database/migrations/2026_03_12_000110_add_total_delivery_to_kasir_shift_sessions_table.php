<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kasir_shift_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('kasir_shift_sessions', 'total_delivery')) {
                $table->decimal('total_delivery', 14, 2)->default(0)->after('total_debit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kasir_shift_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('kasir_shift_sessions', 'total_delivery')) {
                $table->dropColumn('total_delivery');
            }
        });
    }
};
