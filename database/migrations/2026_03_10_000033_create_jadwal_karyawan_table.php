<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function detectKaryawanIdType(): array
    {
        $isBigint = true;
        $isUnsigned = true;

        try {
            $driver = DB::getDriverName();
        } catch (\Throwable $e) {
            $driver = null;
        }

        if ($driver === 'mysql') {
            try {
                $row = DB::table('information_schema.COLUMNS')
                    ->select(['DATA_TYPE', 'COLUMN_TYPE'])
                    ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
                    ->where('TABLE_NAME', 'karyawan')
                    ->where('COLUMN_NAME', 'id_karyawan')
                    ->first();
                if ($row) {
                    $dataType = strtolower((string) ($row->DATA_TYPE ?? ''));
                    $columnType = strtolower((string) ($row->COLUMN_TYPE ?? ''));
                    $isBigint = str_contains($dataType, 'bigint');
                    $isUnsigned = str_contains($columnType, 'unsigned');
                }
            } catch (\Throwable $e) {
                // Fallback below.
            }
        } else {
            try {
                $column = Schema::getColumnType('karyawan', 'id_karyawan');
                $isBigint = strtolower((string) $column) === 'bigint';
                $isUnsigned = true;
            } catch (\Throwable $e) {
                // Ignore; use default.
            }
        }

        return [$isBigint, $isUnsigned];
    }

    public function up(): void
    {
        if (! Schema::hasTable('karyawan')) {
            return;
        }

        if (Schema::hasTable('jadwal_karyawan')) {
            return;
        }

        [$isBigint, $isUnsigned] = $this->detectKaryawanIdType();

        Schema::create('jadwal_karyawan', function (Blueprint $table) use ($isBigint, $isUnsigned): void {
            $table->id();
            $table->date('tanggal');
            $table->unsignedTinyInteger('shift_ke'); // 1-3

            if ($isBigint) {
                if ($isUnsigned) {
                    $table->unsignedBigInteger('id_karyawan');
                } else {
                    $table->bigInteger('id_karyawan');
                }
            } else {
                if ($isUnsigned) {
                    $table->unsignedInteger('id_karyawan');
                } else {
                    $table->integer('id_karyawan');
                }
            }

            // Unique per day: satu karyawan cuma boleh dijadwalkan di 1 shift per tanggal.
            $table->unique(['tanggal', 'id_karyawan'], 'jadwal_tanggal_karyawan_unique');
            $table->index(['tanggal', 'shift_ke'], 'jadwal_tanggal_shift_index');
            $table->index(['id_karyawan', 'tanggal'], 'jadwal_karyawan_tanggal_index');

            // FK optional, tapi kita coba pasang kalau tipe kolom cocok.
            try {
                $table->foreign('id_karyawan')
                    ->references('id_karyawan')
                    ->on('karyawan')
                    ->cascadeOnDelete();
            } catch (\Throwable $e) {
                // Abaikan jika DB menolak FK karena mismatch.
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_karyawan');
    }
};
