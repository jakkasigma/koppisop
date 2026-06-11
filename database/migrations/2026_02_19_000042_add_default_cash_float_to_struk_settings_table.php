<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('struk_settings')) {
            return;
        }

        Schema::table('struk_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('struk_settings', 'default_cash_float')) {
                $table->decimal('default_cash_float', 14, 2)
                    ->nullable()
                    ->after('active_shift_count');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('struk_settings')) {
            return;
        }

        Schema::table('struk_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('struk_settings', 'default_cash_float')) {
                $table->dropColumn('default_cash_float');
            }
        });
    }
};

