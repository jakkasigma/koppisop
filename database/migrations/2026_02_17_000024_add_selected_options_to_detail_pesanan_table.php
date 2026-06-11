<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_pesanan', function (Blueprint $table): void {
            if (! Schema::hasColumn('detail_pesanan', 'selected_options')) {
                $table->json('selected_options')->nullable()->after('spicy_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('detail_pesanan', function (Blueprint $table): void {
            if (Schema::hasColumn('detail_pesanan', 'selected_options')) {
                $table->dropColumn('selected_options');
            }
        });
    }
};
