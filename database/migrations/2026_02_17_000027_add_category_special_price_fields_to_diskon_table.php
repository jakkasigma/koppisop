<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('diskon')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `diskon` MODIFY `tipe_diskon` ENUM('persen','nominal','harga_kategori') NOT NULL");
        }

        Schema::table('diskon', function (Blueprint $table): void {
            if (! Schema::hasColumn('diskon', 'id_kategori_target')) {
                $table->unsignedInteger('id_kategori_target')->nullable()->after('maksimal_diskon');
            }

            if (! Schema::hasColumn('diskon', 'harga_spesial')) {
                $table->decimal('harga_spesial', 12, 2)->nullable()->after('id_kategori_target');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('diskon')) {
            return;
        }

        Schema::table('diskon', function (Blueprint $table): void {
            if (Schema::hasColumn('diskon', 'id_kategori_target')) {
                $table->dropColumn('id_kategori_target');
            }
            if (Schema::hasColumn('diskon', 'harga_spesial')) {
                $table->dropColumn('harga_spesial');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `diskon` MODIFY `tipe_diskon` ENUM('persen','nominal') NOT NULL");
        }
    }
};
