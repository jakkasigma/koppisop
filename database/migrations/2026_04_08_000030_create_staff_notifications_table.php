<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('id_karyawan')->index();
            $table->string('category', 50)->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('action_url')->nullable();
            $table->string('action_label', 100)->nullable();
            $table->string('event_key')->nullable()->unique();
            $table->timestamp('read_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_notifications');
    }
};
