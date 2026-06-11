<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcement_reads')) {
            return;
        }

        Schema::create('announcement_reads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedBigInteger('karyawan_id');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['announcement_id', 'karyawan_id'], 'announcement_reads_unique');
            $table->index(['karyawan_id', 'read_at'], 'announcement_reads_karyawan_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
    }
};

