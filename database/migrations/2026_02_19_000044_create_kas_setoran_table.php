<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kas_setoran')) {
            return;
        }

        Schema::create('kas_setoran', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('tanggal_setor');
            $table->decimal('nominal', 14, 2)->nullable();
            $table->string('catatan', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tanggal_setor'], 'kas_setoran_tanggal_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kas_setoran');
    }
};

