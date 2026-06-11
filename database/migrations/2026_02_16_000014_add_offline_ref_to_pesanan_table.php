<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pesanan', 'offline_ref')) {
            Schema::table('pesanan', function (Blueprint $table): void {
                $table->string('offline_ref', 64)->nullable()->unique()->after('status_pembayaran');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pesanan', 'offline_ref')) {
            return;
        }

        Schema::table('pesanan', function (Blueprint $table): void {
            $table->dropUnique('pesanan_offline_ref_unique');
            $table->dropColumn('offline_ref');
        });
    }
};
