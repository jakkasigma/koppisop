<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('struk_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('struk_settings', 'payroll_alpha_deduction_full_time')) {
                $table->unsignedBigInteger('payroll_alpha_deduction_full_time')->default(0);
            }

            if (! Schema::hasColumn('struk_settings', 'payroll_alpha_deduction_part_time')) {
                $table->unsignedBigInteger('payroll_alpha_deduction_part_time')->default(0);
            }

            if (! Schema::hasColumn('struk_settings', 'payroll_late_deduction_per_minute')) {
                $table->unsignedBigInteger('payroll_late_deduction_per_minute')->default(0);
            }

            if (! Schema::hasColumn('struk_settings', 'payroll_overtime_rate_full_time')) {
                $table->unsignedBigInteger('payroll_overtime_rate_full_time')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('struk_settings', function (Blueprint $table): void {
            $drops = [];

            foreach ([
                'payroll_alpha_deduction_full_time',
                'payroll_alpha_deduction_part_time',
                'payroll_late_deduction_per_minute',
                'payroll_overtime_rate_full_time',
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
