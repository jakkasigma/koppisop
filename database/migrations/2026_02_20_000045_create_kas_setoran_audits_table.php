<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kas_setoran_audits')) {
            return;
        }

        Schema::create('kas_setoran_audits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('setoran_id')->nullable();
            $table->string('aksi', 50);
            $table->decimal('nominal_lama', 14, 2)->nullable();
            $table->decimal('nominal_baru', 14, 2)->nullable();
            $table->string('catatan_lama')->nullable();
            $table->string('catatan_baru')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('dibuat_pada')->useCurrent();

            $table->index(['setoran_id', 'aksi'], 'kas_setoran_audits_setoran_aksi_idx');
            $table->index(['dibuat_pada'], 'kas_setoran_audits_dibuat_pada_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kas_setoran_audits');
    }
};

