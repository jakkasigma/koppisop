<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_messages')) {
            return;
        }

        Schema::table('staff_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('staff_messages', 'meta')) {
                $table->json('meta')->nullable()->after('message');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff_messages')) {
            return;
        }

        Schema::table('staff_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('staff_messages', 'meta')) {
                $table->dropColumn('meta');
            }
        });
    }
};
