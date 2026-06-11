<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kasir_shift_sessions')) {
            return;
        }

        Schema::create('kasir_shift_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('shift_ke');
            $table->decimal('kas_awal', 14, 2);
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'ended_at'], 'kasir_shift_user_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kasir_shift_sessions');
    }
};
