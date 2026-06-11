<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('struk_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('struk_settings', 'absensi_require_login')) {
                $table->boolean('absensi_require_login')->default(false)->after('enable_payment_debit');
            }
            if (! Schema::hasColumn('struk_settings', 'absensi_require_selfie')) {
                $table->boolean('absensi_require_selfie')->default(false)->after('absensi_require_login');
            }
            if (! Schema::hasColumn('struk_settings', 'absensi_require_geofence')) {
                $table->boolean('absensi_require_geofence')->default(false)->after('absensi_require_selfie');
            }

            if (! Schema::hasColumn('struk_settings', 'absensi_geo_lat')) {
                $table->decimal('absensi_geo_lat', 10, 7)->nullable()->after('absensi_require_geofence');
            }
            if (! Schema::hasColumn('struk_settings', 'absensi_geo_lng')) {
                $table->decimal('absensi_geo_lng', 10, 7)->nullable()->after('absensi_geo_lat');
            }
            if (! Schema::hasColumn('struk_settings', 'absensi_geo_radius_m')) {
                $table->unsignedInteger('absensi_geo_radius_m')->default(150)->after('absensi_geo_lng');
            }
            if (! Schema::hasColumn('struk_settings', 'absensi_geo_max_accuracy_m')) {
                $table->unsignedInteger('absensi_geo_max_accuracy_m')->default(80)->after('absensi_geo_radius_m');
            }

            if (! Schema::hasColumn('struk_settings', 'shift1_start_time')) {
                $table->string('shift1_start_time', 5)->default('07:00')->after('absensi_geo_max_accuracy_m');
            }
            if (! Schema::hasColumn('struk_settings', 'shift2_start_time')) {
                $table->string('shift2_start_time', 5)->default('15:00')->after('shift1_start_time');
            }
            if (! Schema::hasColumn('struk_settings', 'shift3_start_time')) {
                $table->string('shift3_start_time', 5)->default('23:00')->after('shift2_start_time');
            }
            if (! Schema::hasColumn('struk_settings', 'absensi_late_tolerance_minutes')) {
                $table->unsignedInteger('absensi_late_tolerance_minutes')->default(10)->after('shift3_start_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('struk_settings', function (Blueprint $table): void {
            foreach ([
                'absensi_require_login',
                'absensi_require_selfie',
                'absensi_require_geofence',
                'absensi_geo_lat',
                'absensi_geo_lng',
                'absensi_geo_radius_m',
                'absensi_geo_max_accuracy_m',
                'shift1_start_time',
                'shift2_start_time',
                'shift3_start_time',
                'absensi_late_tolerance_minutes',
            ] as $col) {
                if (Schema::hasColumn('struk_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

