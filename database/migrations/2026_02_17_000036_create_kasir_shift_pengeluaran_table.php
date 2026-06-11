<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kasir_shift_pengeluaran')) {
            return;
        }

        Schema::create('kasir_shift_pengeluaran', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kasir_shift_session_id')->constrained('kasir_shift_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('nominal', 14, 2);
            $table->string('keterangan', 200)->nullable();
            $table->dateTime('pengeluaran_at');
            $table->timestamps();

            $table->index(['kasir_shift_session_id', 'pengeluaran_at'], 'shift_pengeluaran_shift_waktu_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kasir_shift_pengeluaran');
    }
};
