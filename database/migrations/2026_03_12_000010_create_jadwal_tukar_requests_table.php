<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jadwal_tukar_requests')) {
            return;
        }

        Schema::create('jadwal_tukar_requests', function (Blueprint $table): void {
            $table->id();
            $table->date('tanggal');
            $table->unsignedTinyInteger('from_shift');
            $table->unsignedTinyInteger('to_shift');
            $table->unsignedBigInteger('from_karyawan_id');
            $table->unsignedBigInteger('to_karyawan_id');
            $table->string('status', 20)->default('pending'); // pending|approved|rejected|canceled
            $table->text('note')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'tanggal'], 'jadwal_tukar_status_tanggal_idx');
            $table->index(['from_karyawan_id', 'tanggal'], 'jadwal_tukar_from_tanggal_idx');
            $table->index(['to_karyawan_id', 'tanggal'], 'jadwal_tukar_to_tanggal_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_tukar_requests');
    }
};

