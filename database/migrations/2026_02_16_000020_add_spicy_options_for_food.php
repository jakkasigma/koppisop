<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $table): void {
            if (! Schema::hasColumn('produk', 'is_spicy_enabled')) {
                $table->boolean('is_spicy_enabled')->default(false)->after('is_cup_size_enabled');
            }
        });

        Schema::table('detail_pesanan', function (Blueprint $table): void {
            if (! Schema::hasColumn('detail_pesanan', 'spicy_level')) {
                $table->string('spicy_level', 20)->nullable()->after('cup_size');
            }
        });
    }

    public function down(): void
    {
        Schema::table('detail_pesanan', function (Blueprint $table): void {
            if (Schema::hasColumn('detail_pesanan', 'spicy_level')) {
                $table->dropColumn('spicy_level');
            }
        });

        Schema::table('produk', function (Blueprint $table): void {
            if (Schema::hasColumn('produk', 'is_spicy_enabled')) {
                $table->dropColumn('is_spicy_enabled');
            }
        });
    }
};
