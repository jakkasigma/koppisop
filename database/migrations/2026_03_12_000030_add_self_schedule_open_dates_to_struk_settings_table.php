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
            if (! Schema::hasColumn('struk_settings', 'self_schedule_open_start_date')) {
                $table->date('self_schedule_open_start_date')->nullable()->after('self_schedule_pick_end_date');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_open_end_date')) {
                $table->date('self_schedule_open_end_date')->nullable()->after('self_schedule_open_start_date');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('struk_settings')) {
            return;
        }

        Schema::table('struk_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('struk_settings', 'self_schedule_open_end_date')) {
                $table->dropColumn('self_schedule_open_end_date');
            }
            if (Schema::hasColumn('struk_settings', 'self_schedule_open_start_date')) {
                $table->dropColumn('self_schedule_open_start_date');
            }
        });
    }
};
