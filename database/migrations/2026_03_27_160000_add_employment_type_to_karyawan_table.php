<?php

use App\Models\Karyawan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('karyawan', 'employment_type')) {
            return;
        }

        Schema::table('karyawan', function (Blueprint $table): void {
            $table->string('employment_type', 20)
                ->default(Karyawan::EMPLOYMENT_FULL_TIME)
                ->after('no_telepon');
        });

        DB::table('karyawan')
            ->whereNull('employment_type')
            ->update(['employment_type' => Karyawan::EMPLOYMENT_FULL_TIME]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('karyawan', 'employment_type')) {
            return;
        }

        Schema::table('karyawan', function (Blueprint $table): void {
            $table->dropColumn('employment_type');
        });
    }
};
