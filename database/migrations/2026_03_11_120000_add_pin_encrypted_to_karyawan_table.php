<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table): void {
            if (!Schema::hasColumn('karyawan', 'pin_encrypted')) {
                // Store encrypted PIN so admin can temporarily reveal it.
                // We still use pin_digest for login verification.
                $table->text('pin_encrypted')->nullable()->after('pin_digest');
            }
        });
    }

    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table): void {
            if (Schema::hasColumn('karyawan', 'pin_encrypted')) {
                $table->dropColumn('pin_encrypted');
            }
        });
    }
};

