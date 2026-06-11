<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('struk_settings')) {
            Schema::create('struk_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('nama_toko', 120)->default('KOPISOP');
                $table->string('alamat_toko', 255)->nullable();
                $table->text('header_text')->nullable();
                $table->text('footer_text')->nullable();
                $table->string('logo_path')->nullable();
                $table->boolean('show_logo')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('struk_settings') && DB::table('struk_settings')->count() === 0) {
            DB::table('struk_settings')->insert([
                'nama_toko' => 'KOPISOP',
                'show_logo' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('struk_settings');
    }
};

