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
            if (! Schema::hasColumn('struk_settings', 'enable_tax')) {
                $table->boolean('enable_tax')->default(false)->after('active_shift_count');
            }
            if (! Schema::hasColumn('struk_settings', 'tax_percent')) {
                $table->decimal('tax_percent', 5, 2)->default(0)->after('enable_tax');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('struk_settings')) {
            return;
        }

        Schema::table('struk_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('struk_settings', 'tax_percent')) {
                $table->dropColumn('tax_percent');
            }
            if (Schema::hasColumn('struk_settings', 'enable_tax')) {
                $table->dropColumn('enable_tax');
            }
        });
    }
};

