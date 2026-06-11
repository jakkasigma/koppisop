<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('diskon')) {
            return;
        }

        Schema::table('diskon', function (Blueprint $table): void {
            if (! Schema::hasColumn('diskon', 'maksimal_diskon')) {
                $table->decimal('maksimal_diskon', 12, 2)->nullable()->after('minimal_belanja');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('diskon') || ! Schema::hasColumn('diskon', 'maksimal_diskon')) {
            return;
        }

        Schema::table('diskon', function (Blueprint $table): void {
            $table->dropColumn('maksimal_diskon');
        });
    }
};
