<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $table): void {
            if (! Schema::hasColumn('produk', 'is_temperature_enabled')) {
                $table->boolean('is_temperature_enabled')->default(false)->after('stok');
            }

            if (! Schema::hasColumn('produk', 'is_sugar_enabled')) {
                $table->boolean('is_sugar_enabled')->default(false)->after('is_temperature_enabled');
            }

            if (! Schema::hasColumn('produk', 'is_cup_size_enabled')) {
                $table->boolean('is_cup_size_enabled')->default(false)->after('is_sugar_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table): void {
            $drops = [];

            if (Schema::hasColumn('produk', 'is_temperature_enabled')) {
                $drops[] = 'is_temperature_enabled';
            }

            if (Schema::hasColumn('produk', 'is_sugar_enabled')) {
                $drops[] = 'is_sugar_enabled';
            }

            if (Schema::hasColumn('produk', 'is_cup_size_enabled')) {
                $drops[] = 'is_cup_size_enabled';
            }

            if (! empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
