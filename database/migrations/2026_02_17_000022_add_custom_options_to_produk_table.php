<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $table): void {
            if (! Schema::hasColumn('produk', 'temperature_options')) {
                $table->json('temperature_options')->nullable()->after('is_temperature_enabled');
            }
            if (! Schema::hasColumn('produk', 'sugar_options')) {
                $table->json('sugar_options')->nullable()->after('is_sugar_enabled');
            }
            if (! Schema::hasColumn('produk', 'cup_size_options')) {
                $table->json('cup_size_options')->nullable()->after('is_cup_size_enabled');
            }
            if (! Schema::hasColumn('produk', 'spicy_options')) {
                $table->json('spicy_options')->nullable()->after('is_spicy_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table): void {
            $drops = [];

            if (Schema::hasColumn('produk', 'temperature_options')) {
                $drops[] = 'temperature_options';
            }
            if (Schema::hasColumn('produk', 'sugar_options')) {
                $drops[] = 'sugar_options';
            }
            if (Schema::hasColumn('produk', 'cup_size_options')) {
                $drops[] = 'cup_size_options';
            }
            if (Schema::hasColumn('produk', 'spicy_options')) {
                $drops[] = 'spicy_options';
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
