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
            if (! Schema::hasColumn('pesanan', 'kasir_shift_session_id')) {
                $table->foreignId('kasir_shift_session_id')
                    ->nullable()
                    ->after('id_karyawan')
                    ->constrained('kasir_shift_sessions')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('pesanan', 'no_urut_shift')) {
                $table->unsignedInteger('no_urut_shift')->nullable()->after('kasir_shift_session_id');
            }

            $table->index(['kasir_shift_session_id', 'no_urut_shift'], 'pesanan_shift_order_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pesanan')) {
            return;
        }

        Schema::table('pesanan', function (Blueprint $table): void {
            try {
                $table->dropIndex('pesanan_shift_order_index');
            } catch (\Throwable $e) {
            }

            if (Schema::hasColumn('pesanan', 'kasir_shift_session_id')) {
                try {
                    $table->dropForeign(['kasir_shift_session_id']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('kasir_shift_session_id');
            }

            if (Schema::hasColumn('pesanan', 'no_urut_shift')) {
                $table->dropColumn('no_urut_shift');
            }
        });
    }
};

