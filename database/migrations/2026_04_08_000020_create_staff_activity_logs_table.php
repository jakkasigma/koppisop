<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff_activity_logs')) {
            return;
        }

        Schema::create('staff_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('karyawan_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('employment_type')->nullable();
            $table->string('action_key')->index();
            $table->string('action_label');
            $table->text('summary');
            $table->string('target_type')->nullable();
            $table->string('target_label')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_activity_logs');
    }
};
