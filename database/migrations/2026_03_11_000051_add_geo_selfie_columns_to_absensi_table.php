<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi', function (Blueprint $table): void {
            if (! Schema::hasColumn('absensi', 'status')) {
                $table->string('status', 20)->nullable()->after('catatan');
            }
            if (! Schema::hasColumn('absensi', 'shift_no')) {
                $table->unsignedTinyInteger('shift_no')->nullable()->after('status');
            }
            if (! Schema::hasColumn('absensi', 'selfie_path')) {
                $table->string('selfie_path', 255)->nullable()->after('shift_no');
            }
            if (! Schema::hasColumn('absensi', 'geo_lat')) {
                $table->decimal('geo_lat', 10, 7)->nullable()->after('selfie_path');
            }
            if (! Schema::hasColumn('absensi', 'geo_lng')) {
                $table->decimal('geo_lng', 10, 7)->nullable()->after('geo_lat');
            }
            if (! Schema::hasColumn('absensi', 'geo_accuracy_m')) {
                $table->unsignedInteger('geo_accuracy_m')->nullable()->after('geo_lng');
            }
        });
    }

    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table): void {
            foreach (['status', 'shift_no', 'selfie_path', 'geo_lat', 'geo_lng', 'geo_accuracy_m'] as $col) {
                if (Schema::hasColumn('absensi', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

