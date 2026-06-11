<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_slips', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_slips', 'approved_leave_shift_count')) {
                $table->unsignedInteger('approved_leave_shift_count')->default(0)->after('alpha_shift_count');
            }

            if (! Schema::hasColumn('payroll_slips', 'approved_leave_day_count')) {
                $table->unsignedInteger('approved_leave_day_count')->default(0)->after('approved_leave_shift_count');
            }

            if (! Schema::hasColumn('payroll_slips', 'auto_approved_leave_deduction')) {
                $table->unsignedBigInteger('auto_approved_leave_deduction')->default(0)->after('auto_alpha_deduction');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_slips', function (Blueprint $table) {
            foreach ([
                'approved_leave_shift_count',
                'approved_leave_day_count',
                'auto_approved_leave_deduction',
            ] as $column) {
                if (Schema::hasColumn('payroll_slips', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
