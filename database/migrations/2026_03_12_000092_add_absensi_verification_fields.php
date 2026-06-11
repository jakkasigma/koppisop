<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi', function (Blueprint $table): void {
            $table->string('verification_status', 20)->default('pending')->after('status');
            $table->text('verification_note')->nullable()->after('verification_status');
            $table->unsignedBigInteger('verified_by')->nullable()->after('verification_note');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table): void {
            $table->dropColumn(['verification_status', 'verification_note', 'verified_by', 'verified_at']);
        });
    }
};
