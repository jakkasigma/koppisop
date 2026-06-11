<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jadwal_tukar_requests')) {
            return;
        }

        Schema::table('jadwal_tukar_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('jadwal_tukar_requests', 'from_tanggal')) {
                $table->date('from_tanggal')->nullable()->after('tanggal');
            }
            if (! Schema::hasColumn('jadwal_tukar_requests', 'to_tanggal')) {
                $table->date('to_tanggal')->nullable()->after('from_tanggal');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('jadwal_tukar_requests')) {
            return;
        }

        Schema::table('jadwal_tukar_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('jadwal_tukar_requests', 'to_tanggal')) {
                $table->dropColumn('to_tanggal');
            }
            if (Schema::hasColumn('jadwal_tukar_requests', 'from_tanggal')) {
                $table->dropColumn('from_tanggal');
            }
        });
    }
};

