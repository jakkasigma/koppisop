<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cafe_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_cafe', 120);
            $table->string('tagline', 120)->nullable();
            $table->string('alamat', 255)->nullable();
            $table->string('kota', 80)->nullable();
            $table->string('telepon', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('instagram', 80)->nullable();
            $table->string('website', 120)->nullable();
            $table->string('logo_path')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cafe_profiles');
    }
};
