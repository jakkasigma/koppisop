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
            if (! Schema::hasColumn('struk_settings', 'part_time_shift1_start_time')) {
                $table->string('part_time_shift1_start_time', 5)->nullable()->after('shift3_start_time');
            }
            if (! Schema::hasColumn('struk_settings', 'part_time_shift2_start_time')) {
                $table->string('part_time_shift2_start_time', 5)->nullable()->after('part_time_shift1_start_time');
            }
            if (! Schema::hasColumn('struk_settings', 'part_time_shift3_start_time')) {
                $table->string('part_time_shift3_start_time', 5)->nullable()->after('part_time_shift2_start_time');
            }

            if (! Schema::hasColumn('struk_settings', 'self_schedule_part_time_capacity_shift1')) {
                $table->unsignedInteger('self_schedule_part_time_capacity_shift1')->nullable()->after('self_schedule_capacity_shift3');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_part_time_capacity_shift2')) {
                $table->unsignedInteger('self_schedule_part_time_capacity_shift2')->nullable()->after('self_schedule_part_time_capacity_shift1');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_part_time_capacity_shift3')) {
                $table->unsignedInteger('self_schedule_part_time_capacity_shift3')->nullable()->after('self_schedule_part_time_capacity_shift2');
            }

            if (! Schema::hasColumn('struk_settings', 'self_schedule_part_time_min_per_week')) {
                $table->unsignedInteger('self_schedule_part_time_min_per_week')->nullable()->after('self_schedule_max_per_month');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_part_time_max_per_week')) {
                $table->unsignedInteger('self_schedule_part_time_max_per_week')->nullable()->after('self_schedule_part_time_min_per_week');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_part_time_min_per_month')) {
                $table->unsignedInteger('self_schedule_part_time_min_per_month')->nullable()->after('self_schedule_part_time_max_per_week');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_part_time_max_per_month')) {
                $table->unsignedInteger('self_schedule_part_time_max_per_month')->nullable()->after('self_schedule_part_time_min_per_month');
            }

            if (! Schema::hasColumn('struk_settings', 'self_schedule_part_time_capacity_weekend_shift1')) {
                $table->unsignedInteger('self_schedule_part_time_capacity_weekend_shift1')->nullable()->after('self_schedule_capacity_weekend_shift3');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_part_time_capacity_weekend_shift2')) {
                $table->unsignedInteger('self_schedule_part_time_capacity_weekend_shift2')->nullable()->after('self_schedule_part_time_capacity_weekend_shift1');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_part_time_capacity_weekend_shift3')) {
                $table->unsignedInteger('self_schedule_part_time_capacity_weekend_shift3')->nullable()->after('self_schedule_part_time_capacity_weekend_shift2');
            }
        });

        DB::table('struk_settings')
            ->whereNull('part_time_shift1_start_time')
            ->update(['part_time_shift1_start_time' => '07:00']);
        DB::table('struk_settings')
            ->whereNull('part_time_shift2_start_time')
            ->update(['part_time_shift2_start_time' => '11:30']);
        DB::table('struk_settings')
            ->whereNull('part_time_shift3_start_time')
            ->update(['part_time_shift3_start_time' => '16:00']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('struk_settings')) {
            return;
        }

        Schema::table('struk_settings', function (Blueprint $table): void {
            $drops = [];

            foreach ([
                'part_time_shift1_start_time',
                'part_time_shift2_start_time',
                'part_time_shift3_start_time',
                'self_schedule_part_time_capacity_shift1',
                'self_schedule_part_time_capacity_shift2',
                'self_schedule_part_time_capacity_shift3',
                'self_schedule_part_time_min_per_week',
                'self_schedule_part_time_max_per_week',
                'self_schedule_part_time_min_per_month',
                'self_schedule_part_time_max_per_month',
                'self_schedule_part_time_capacity_weekend_shift1',
                'self_schedule_part_time_capacity_weekend_shift2',
                'self_schedule_part_time_capacity_weekend_shift3',
            ] as $column) {
                if (Schema::hasColumn('struk_settings', $column)) {
                    $drops[] = $column;
                }
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
