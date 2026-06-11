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
            if (! Schema::hasColumn('struk_settings', 'theme_primary')) {
                $table->string('theme_primary', 20)->nullable()->after('nama_cabang');
            }
            if (! Schema::hasColumn('struk_settings', 'theme_secondary')) {
                $table->string('theme_secondary', 20)->nullable()->after('theme_primary');
            }
            if (! Schema::hasColumn('struk_settings', 'theme_bg')) {
                $table->string('theme_bg', 20)->nullable()->after('theme_secondary');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('struk_settings')) {
            return;
        }

        Schema::table('struk_settings', function (Blueprint $table): void {
            foreach (['theme_bg', 'theme_secondary', 'theme_primary'] as $col) {
                if (Schema::hasColumn('struk_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
