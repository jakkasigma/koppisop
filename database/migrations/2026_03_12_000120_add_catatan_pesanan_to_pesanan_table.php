<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pesanan')) {
            return;
        }

        Schema::table('pesanan', function (Blueprint $table): void {
            if (! Schema::hasColumn('pesanan', 'catatan_pesanan')) {
                $table->string('catatan_pesanan', 255)->nullable()->after('status_pembayaran');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pesanan')) {
            return;
        }

        Schema::table('pesanan', function (Blueprint $table): void {
            if (Schema::hasColumn('pesanan', 'catatan_pesanan')) {
                $table->dropColumn('catatan_pesanan');
            }
        });
    }
};
