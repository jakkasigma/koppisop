<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasColumn('detail_pesanan', 'id_detail')) {
            DB::statement('ALTER TABLE detail_pesanan ADD INDEX detail_pesanan_id_pesanan_index (id_pesanan)');
            DB::statement('ALTER TABLE detail_pesanan ADD INDEX detail_pesanan_id_produk_index (id_produk)');
            DB::statement('ALTER TABLE detail_pesanan DROP PRIMARY KEY');
            DB::statement('ALTER TABLE detail_pesanan ADD COLUMN id_detail BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasColumn('detail_pesanan', 'id_detail')) {
            DB::statement('ALTER TABLE detail_pesanan DROP PRIMARY KEY');
            DB::statement('ALTER TABLE detail_pesanan DROP COLUMN id_detail');
            DB::statement('ALTER TABLE detail_pesanan DROP INDEX detail_pesanan_id_pesanan_index');
            DB::statement('ALTER TABLE detail_pesanan DROP INDEX detail_pesanan_id_produk_index');
            DB::statement('ALTER TABLE detail_pesanan ADD PRIMARY KEY (id_pesanan, id_produk)');
        }
    }
};
