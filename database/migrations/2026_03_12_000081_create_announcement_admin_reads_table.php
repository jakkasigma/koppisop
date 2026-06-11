<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcement_admin_reads')) {
            return;
        }

        Schema::create('announcement_admin_reads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id'], 'announcement_admin_reads_unique');
            $table->index(['user_id', 'read_at'], 'announcement_admin_reads_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_admin_reads');
    }
};
