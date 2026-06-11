<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_slips', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_slips', 'late_count')) {
                $table->unsignedInteger('late_count')->default(0)->after('alpha_shift_count');
            }

            if (! Schema::hasColumn('payroll_slips', 'late_minutes')) {
                $table->unsignedInteger('late_minutes')->default(0)->after('late_count');
            }

            if (! Schema::hasColumn('payroll_slips', 'overtime_shift_count')) {
                $table->unsignedInteger('overtime_shift_count')->default(0)->after('late_minutes');
            }

            if (! Schema::hasColumn('payroll_slips', 'overtime_minutes')) {
                $table->unsignedInteger('overtime_minutes')->default(0)->after('overtime_shift_count');
            }

            if (! Schema::hasColumn('payroll_slips', 'overtime_rate')) {
                $table->unsignedBigInteger('overtime_rate')->default(0)->after('overtime_minutes');
            }

            if (! Schema::hasColumn('payroll_slips', 'overtime_amount')) {
                $table->unsignedBigInteger('overtime_amount')->default(0)->after('overtime_rate');
            }

            if (! Schema::hasColumn('payroll_slips', 'auto_alpha_deduction')) {
                $table->unsignedBigInteger('auto_alpha_deduction')->default(0)->after('overtime_amount');
            }

            if (! Schema::hasColumn('payroll_slips', 'auto_late_deduction')) {
                $table->unsignedBigInteger('auto_late_deduction')->default(0)->after('auto_alpha_deduction');
            }

            if (! Schema::hasColumn('payroll_slips', 'auto_deduction_amount')) {
                $table->unsignedBigInteger('auto_deduction_amount')->default(0)->after('auto_late_deduction');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_slips', function (Blueprint $table): void {
            $drops = [];

            foreach ([
                'late_count',
                'late_minutes',
                'overtime_shift_count',
                'overtime_minutes',
                'overtime_rate',
                'overtime_amount',
                'auto_alpha_deduction',
                'auto_late_deduction',
                'auto_deduction_amount',
            ] as $column) {
                if (Schema::hasColumn('payroll_slips', $column)) {
                    $drops[] = $column;
                }
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
