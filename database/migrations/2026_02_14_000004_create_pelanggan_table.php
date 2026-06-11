<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pelanggan')) {
            return;
        }

        Schema::create('pelanggan', function (Blueprint $table) {
            $table->id('id_pelanggan');
            $table->string('nama', 100)->nullable();
            $table->string('username_ig', 100)->nullable();
            $table->string('no_telepon', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelanggan');
    }
};
