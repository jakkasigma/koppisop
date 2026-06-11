<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $table): void {
            if (! Schema::hasColumn('produk', 'custom_option_groups')) {
                $table->json('custom_option_groups')->nullable()->after('spicy_options');
            }
        });
    }

    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table): void {
            if (Schema::hasColumn('produk', 'custom_option_groups')) {
                $table->dropColumn('custom_option_groups');
            }
        });
    }
};
