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
            if (! Schema::hasColumn('pesanan', 'pajak_persen')) {
                $table->decimal('pajak_persen', 5, 2)->default(0)->after('diskon_nilai');
            }
            if (! Schema::hasColumn('pesanan', 'pajak_nominal')) {
                $table->decimal('pajak_nominal', 12, 2)->default(0)->after('pajak_persen');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pesanan')) {
            return;
        }

        Schema::table('pesanan', function (Blueprint $table): void {
            if (Schema::hasColumn('pesanan', 'pajak_nominal')) {
                $table->dropColumn('pajak_nominal');
            }
            if (Schema::hasColumn('pesanan', 'pajak_persen')) {
                $table->dropColumn('pajak_persen');
            }
        });
    }
};

