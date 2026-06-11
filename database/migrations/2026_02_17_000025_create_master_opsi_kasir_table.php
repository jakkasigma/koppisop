<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_opsi_kasir', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_opsi', 30)->unique();
            $table->string('nama_opsi', 50);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('urutan')->default(0);
            $table->json('opsi')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_opsi_kasir');
    }
};
