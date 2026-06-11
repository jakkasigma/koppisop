<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();
        $driver = (string) $connection->getDriverName();

        $columnType = '';
        $isUnsigned = false;
        $isBigint = false;

        if ($driver === 'mysql') {
            $database = $connection->getDatabaseName();
            $col = DB::selectOne(
                "select COLUMN_TYPE as column_type
                from information_schema.COLUMNS
                where TABLE_SCHEMA = ?
                  and TABLE_NAME = 'karyawan'
                  and COLUMN_NAME = 'id_karyawan'",
                [$database]
            );
            $columnType = strtolower((string) ($col->column_type ?? ''));
            $isUnsigned = str_contains($columnType, 'unsigned');
            $isBigint = str_contains($columnType, 'bigint');
        } else {
            // Non-MySQL (mis. sqlite untuk testing): gunakan schema builder agar portable.
            $simpleType = strtolower((string) $connection->getSchemaBuilder()->getColumnType('karyawan', 'id_karyawan'));
            $columnType = $simpleType;
            $isUnsigned = false;
            $isBigint = str_contains($simpleType, 'big');
        }

        Schema::create('absensi', function (Blueprint $table) use ($isBigint, $isUnsigned) {
            $table->id('id_absensi');
            if ($isBigint) {
                $isUnsigned ? $table->unsignedBigInteger('id_karyawan') : $table->bigInteger('id_karyawan');
            } else {
                $isUnsigned ? $table->unsignedInteger('id_karyawan') : $table->integer('id_karyawan');
            }
            $table->date('tanggal');
            $table->dateTime('waktu_masuk')->nullable();
            $table->dateTime('waktu_pulang')->nullable();
            $table->string('catatan', 255)->nullable();

            $table->foreign('id_karyawan')->references('id_karyawan')->on('karyawan');
            $table->unique(['id_karyawan', 'tanggal'], 'absensi_karyawan_tanggal_unique');
            $table->index(['tanggal'], 'absensi_tanggal_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi');
    }
};
