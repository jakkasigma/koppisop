<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'paper_preference')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('paper_preference', 3)->default('80')->after('role');
            });
        }

        DB::table('users')
            ->whereNull('paper_preference')
            ->update(['paper_preference' => '80']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'paper_preference')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('paper_preference');
            });
        }
    }
};
