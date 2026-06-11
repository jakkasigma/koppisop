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
            if (! Schema::hasColumn('struk_settings', 'operasional_reset_hour')) {
                $table->unsignedTinyInteger('operasional_reset_hour')->default(3)->after('show_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('struk_settings')) {
            return;
        }

        Schema::table('struk_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('struk_settings', 'operasional_reset_hour')) {
                $table->dropColumn('operasional_reset_hour');
            }
        });
    }
};

