<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_message_reads', function (Blueprint $table): void {
            $table->id();
            $table->string('thread_type', 30);
            $table->unsignedBigInteger('thread_id');
            $table->string('reader_role', 20);
            $table->unsignedBigInteger('reader_karyawan_id')->nullable();
            $table->unsignedBigInteger('reader_user_id')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->index(['thread_type', 'thread_id']);
            $table->index(['reader_role']);
            $table->unique(['thread_type', 'thread_id', 'reader_role', 'reader_karyawan_id', 'reader_user_id'], 'staff_message_reads_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_message_reads');
    }
};
