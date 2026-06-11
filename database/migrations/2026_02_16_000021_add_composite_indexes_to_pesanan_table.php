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
            try {
                $table->index(
                    ['waktu_pembayaran', 'id_karyawan'],
                    'pesanan_waktu_karyawan_index'
                );
            } catch (\Throwable) {
                // index already exists
            }

            try {
                $table->index(
                    ['status_pembayaran', 'waktu_pembayaran'],
                    'pesanan_status_waktu_index'
                );
            } catch (\Throwable) {
                // index already exists
            }

            try {
                $table->index(
                    ['status_pembayaran', 'waktu_pembayaran', 'metode_pembayaran'],
                    'pesanan_status_waktu_metode_index'
                );
            } catch (\Throwable) {
                // index already exists
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pesanan')) {
            return;
        }

        Schema::table('pesanan', function (Blueprint $table): void {
            try {
                $table->dropIndex('pesanan_waktu_karyawan_index');
            } catch (\Throwable) {
                // index doesn't exist
            }

            try {
                $table->dropIndex('pesanan_status_waktu_index');
            } catch (\Throwable) {
                // index doesn't exist
            }

            try {
                $table->dropIndex('pesanan_status_waktu_metode_index');
            } catch (\Throwable) {
                // index doesn't exist
            }
        });
    }
};
