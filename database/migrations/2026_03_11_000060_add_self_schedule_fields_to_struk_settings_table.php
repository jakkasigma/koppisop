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
            if (! Schema::hasColumn('struk_settings', 'self_schedule_enabled')) {
                $table->boolean('self_schedule_enabled')->default(false)->after('absensi_late_tolerance_minutes');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_is_open')) {
                $table->boolean('self_schedule_is_open')->default(false)->after('self_schedule_enabled');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_pick_start_date')) {
                $table->date('self_schedule_pick_start_date')->nullable()->after('self_schedule_is_open');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_pick_end_date')) {
                $table->date('self_schedule_pick_end_date')->nullable()->after('self_schedule_pick_start_date');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_open_start_date')) {
                $table->date('self_schedule_open_start_date')->nullable()->after('self_schedule_pick_end_date');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_open_end_date')) {
                $table->date('self_schedule_open_end_date')->nullable()->after('self_schedule_open_start_date');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_capacity_shift1')) {
                $table->unsignedInteger('self_schedule_capacity_shift1')->default(1)->after('self_schedule_open_end_date');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_capacity_shift2')) {
                $table->unsignedInteger('self_schedule_capacity_shift2')->default(1)->after('self_schedule_capacity_shift1');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_capacity_shift3')) {
                $table->unsignedInteger('self_schedule_capacity_shift3')->default(1)->after('self_schedule_capacity_shift2');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('struk_settings')) {
            return;
        }

        Schema::table('struk_settings', function (Blueprint $table): void {
            foreach ([
                'self_schedule_capacity_shift3',
                'self_schedule_capacity_shift2',
                'self_schedule_capacity_shift1',
                'self_schedule_open_end_date',
                'self_schedule_open_start_date',
                'self_schedule_pick_end_date',
                'self_schedule_pick_start_date',
                'self_schedule_is_open',
                'self_schedule_enabled',
            ] as $col) {
                if (Schema::hasColumn('struk_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
