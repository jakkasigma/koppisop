<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('thread_type', 30);
            $table->unsignedBigInteger('thread_id');
            $table->string('sender_role', 20);
            $table->unsignedBigInteger('sender_karyawan_id')->nullable();
            $table->unsignedBigInteger('sender_user_id')->nullable();
            $table->text('message');
            $table->timestamps();

            $table->index(['thread_type', 'thread_id']);
            $table->index(['sender_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_messages');
    }
};
