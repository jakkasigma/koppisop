<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('struk_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('struk_settings', 'absensi_checkin_before_minutes')) {
                $table->unsignedInteger('absensi_checkin_before_minutes')->default(30)->after('absensi_late_tolerance_minutes');
            }
            if (! Schema::hasColumn('struk_settings', 'absensi_checkin_after_minutes')) {
                $table->unsignedInteger('absensi_checkin_after_minutes')->default(60)->after('absensi_checkin_before_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('struk_settings', function (Blueprint $table): void {
            foreach ([
                'absensi_checkin_before_minutes',
                'absensi_checkin_after_minutes',
            ] as $col) {
                if (Schema::hasColumn('struk_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
