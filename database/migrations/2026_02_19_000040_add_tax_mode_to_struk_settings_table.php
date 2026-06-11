<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('struk_settings')) {
            return;
        }

        Schema::table('struk_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('struk_settings', 'tax_mode')) {
                $table->string('tax_mode', 20)->default('transaksi')->after('tax_percent');
            }
        });

        DB::table('struk_settings')
            ->whereNull('tax_mode')
            ->update(['tax_mode' => 'transaksi']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('struk_settings')) {
            return;
        }

        Schema::table('struk_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('struk_settings', 'tax_mode')) {
                $table->dropColumn('tax_mode');
            }
        });
    }
};

