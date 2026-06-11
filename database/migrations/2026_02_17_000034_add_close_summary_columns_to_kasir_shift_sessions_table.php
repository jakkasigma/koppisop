<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kasir_shift_sessions')) {
            return;
        }

        Schema::table('kasir_shift_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('kasir_shift_sessions', 'total_trx')) {
                $table->unsignedInteger('total_trx')->default(0)->after('ended_at');
            }
            if (! Schema::hasColumn('kasir_shift_sessions', 'total_omzet')) {
                $table->decimal('total_omzet', 14, 2)->default(0)->after('total_trx');
            }
            if (! Schema::hasColumn('kasir_shift_sessions', 'total_cash')) {
                $table->decimal('total_cash', 14, 2)->default(0)->after('total_omzet');
            }
            if (! Schema::hasColumn('kasir_shift_sessions', 'total_qris')) {
                $table->decimal('total_qris', 14, 2)->default(0)->after('total_cash');
            }
            if (! Schema::hasColumn('kasir_shift_sessions', 'total_debit')) {
                $table->decimal('total_debit', 14, 2)->default(0)->after('total_qris');
            }
            if (! Schema::hasColumn('kasir_shift_sessions', 'estimasi_kas_akhir')) {
                $table->decimal('estimasi_kas_akhir', 14, 2)->nullable()->after('total_debit');
            }
            if (! Schema::hasColumn('kasir_shift_sessions', 'kas_akhir_input')) {
                $table->decimal('kas_akhir_input', 14, 2)->nullable()->after('estimasi_kas_akhir');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('kasir_shift_sessions')) {
            return;
        }

        Schema::table('kasir_shift_sessions', function (Blueprint $table): void {
            foreach ([
                'total_trx',
                'total_omzet',
                'total_cash',
                'total_qris',
                'total_debit',
                'estimasi_kas_akhir',
                'kas_akhir_input',
            ] as $column) {
                if (Schema::hasColumn('kasir_shift_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
