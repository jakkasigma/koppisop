<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcements')) {
            return;
        }

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);
            $table->text('body');
            $table->string('image_path')->nullable();
            $table->string('target_role', 50)->nullable(); // null = semua
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'published_at'], 'announcements_active_published_idx');
            $table->index(['target_role'], 'announcements_target_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};

