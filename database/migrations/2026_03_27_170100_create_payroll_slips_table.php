<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function detectKaryawanIdType(): array
    {
        $connection = DB::connection();
        $driver = (string) $connection->getDriverName();
        $columnType = '';
        $isUnsigned = false;
        $isBigint = false;

        if ($driver === 'mysql') {
            $database = $connection->getDatabaseName();
            $column = DB::selectOne(
                "select COLUMN_TYPE as column_type
                 from information_schema.COLUMNS
                 where TABLE_SCHEMA = ?
                   and TABLE_NAME = 'karyawan'
                   and COLUMN_NAME = 'id_karyawan'",
                [$database]
            );
            $columnType = strtolower((string) ($column->column_type ?? ''));
            $isUnsigned = str_contains($columnType, 'unsigned');
            $isBigint = str_contains($columnType, 'bigint');
        } else {
            $simpleType = strtolower((string) $connection->getSchemaBuilder()->getColumnType('karyawan', 'id_karyawan'));
            $columnType = $simpleType;
            $isUnsigned = false;
            $isBigint = str_contains($columnType, 'big');
        }

        return [$isBigint, $isUnsigned];
    }

    public function up(): void
    {
        if (Schema::hasTable('payroll_slips')) {
            Schema::drop('payroll_slips');
        }

        [$isBigint, $isUnsigned] = $this->detectKaryawanIdType();

        Schema::create('payroll_slips', function (Blueprint $table) use ($isBigint, $isUnsigned): void {
            $table->id();
            if ($isBigint) {
                $isUnsigned ? $table->unsignedBigInteger('id_karyawan') : $table->bigInteger('id_karyawan');
            } else {
                $isUnsigned ? $table->unsignedInteger('id_karyawan') : $table->integer('id_karyawan');
            }
            $table->date('period_month');
            $table->string('employment_type', 20);
            $table->string('salary_scheme', 20);
            $table->unsignedBigInteger('base_amount')->default(0);
            $table->unsignedBigInteger('hourly_rate')->nullable();
            $table->unsignedInteger('paid_minutes')->default(0);
            $table->unsignedInteger('scheduled_shift_count')->default(0);
            $table->unsignedInteger('present_shift_count')->default(0);
            $table->unsignedInteger('alpha_shift_count')->default(0);
            $table->unsignedBigInteger('bonus_amount')->default(0);
            $table->unsignedBigInteger('deduction_amount')->default(0);
            $table->unsignedBigInteger('gross_amount')->default(0);
            $table->unsignedBigInteger('net_amount')->default(0);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['id_karyawan', 'period_month'], 'payroll_karyawan_period_unique');
            $table->index(['period_month', 'status'], 'payroll_period_status_index');
            $table->foreign('id_karyawan')->references('id_karyawan')->on('karyawan')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_slips');
    }
};
