<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_slips', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_slips', 'early_leave_count')) {
                $table->unsignedInteger('early_leave_count')->default(0)->after('late_minutes');
            }

            if (! Schema::hasColumn('payroll_slips', 'early_leave_minutes')) {
                $table->unsignedInteger('early_leave_minutes')->default(0)->after('early_leave_count');
            }

            if (! Schema::hasColumn('payroll_slips', 'auto_early_leave_deduction')) {
                $table->unsignedBigInteger('auto_early_leave_deduction')->default(0)->after('auto_late_deduction');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_slips', function (Blueprint $table): void {
            $drops = [];

            foreach ([
                'early_leave_count',
                'early_leave_minutes',
                'auto_early_leave_deduction',
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
