<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_pesanan', function (Blueprint $table): void {
            if (! Schema::hasColumn('detail_pesanan', 'temperature')) {
                $table->string('temperature', 10)->nullable()->after('harga_satuan');
            }

            if (! Schema::hasColumn('detail_pesanan', 'sugar_level')) {
                $table->string('sugar_level', 20)->nullable()->after('temperature');
            }

            if (! Schema::hasColumn('detail_pesanan', 'cup_size')) {
                $table->string('cup_size', 20)->nullable()->after('sugar_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('detail_pesanan', function (Blueprint $table): void {
            $drops = [];

            if (Schema::hasColumn('detail_pesanan', 'temperature')) {
                $drops[] = 'temperature';
            }

            if (Schema::hasColumn('detail_pesanan', 'sugar_level')) {
                $drops[] = 'sugar_level';
            }

            if (Schema::hasColumn('detail_pesanan', 'cup_size')) {
                $drops[] = 'cup_size';
            }

            if (! empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
