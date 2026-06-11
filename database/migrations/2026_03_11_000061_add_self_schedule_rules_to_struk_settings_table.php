<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('struk_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('struk_settings', 'self_schedule_min_per_week')) {
                $table->unsignedSmallInteger('self_schedule_min_per_week')->nullable()->after('self_schedule_capacity_shift3');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_max_per_week')) {
                $table->unsignedSmallInteger('self_schedule_max_per_week')->nullable()->after('self_schedule_min_per_week');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_min_per_month')) {
                $table->unsignedSmallInteger('self_schedule_min_per_month')->nullable()->after('self_schedule_max_per_week');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_max_per_month')) {
                $table->unsignedSmallInteger('self_schedule_max_per_month')->nullable()->after('self_schedule_min_per_month');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_allow_cancel')) {
                $table->boolean('self_schedule_allow_cancel')->default(false)->after('self_schedule_max_per_month');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_cancel_min_days_before')) {
                $table->unsignedSmallInteger('self_schedule_cancel_min_days_before')->default(0)->after('self_schedule_allow_cancel');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_capacity_weekend_shift1')) {
                $table->unsignedInteger('self_schedule_capacity_weekend_shift1')->nullable()->after('self_schedule_cancel_min_days_before');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_capacity_weekend_shift2')) {
                $table->unsignedInteger('self_schedule_capacity_weekend_shift2')->nullable()->after('self_schedule_capacity_weekend_shift1');
            }
            if (! Schema::hasColumn('struk_settings', 'self_schedule_capacity_weekend_shift3')) {
                $table->unsignedInteger('self_schedule_capacity_weekend_shift3')->nullable()->after('self_schedule_capacity_weekend_shift2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('struk_settings', function (Blueprint $table): void {
            $cols = [
                'self_schedule_capacity_weekend_shift3',
                'self_schedule_capacity_weekend_shift2',
                'self_schedule_capacity_weekend_shift1',
                'self_schedule_cancel_min_days_before',
                'self_schedule_allow_cancel',
                'self_schedule_max_per_month',
                'self_schedule_min_per_month',
                'self_schedule_max_per_week',
                'self_schedule_min_per_week',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('struk_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

