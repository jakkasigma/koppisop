<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('struk_settings')) {
            return;
        }

        Schema::table('struk_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('struk_settings', 'logo_max_width')) {
                $table->unsignedSmallInteger('logo_max_width')->default(120)->after('show_logo');
            }
            if (! Schema::hasColumn('struk_settings', 'show_kode_nota')) {
                $table->boolean('show_kode_nota')->default(true)->after('logo_max_width');
            }
            if (! Schema::hasColumn('struk_settings', 'show_id_pesanan')) {
                $table->boolean('show_id_pesanan')->default(true)->after('show_kode_nota');
            }
            if (! Schema::hasColumn('struk_settings', 'show_waktu')) {
                $table->boolean('show_waktu')->default(true)->after('show_id_pesanan');
            }
            if (! Schema::hasColumn('struk_settings', 'show_pelanggan')) {
                $table->boolean('show_pelanggan')->default(true)->after('show_waktu');
            }
            if (! Schema::hasColumn('struk_settings', 'show_kasir')) {
                $table->boolean('show_kasir')->default(true)->after('show_pelanggan');
            }
            if (! Schema::hasColumn('struk_settings', 'show_metode')) {
                $table->boolean('show_metode')->default(true)->after('show_kasir');
            }
            if (! Schema::hasColumn('struk_settings', 'show_status')) {
                $table->boolean('show_status')->default(true)->after('show_metode');
            }
            if (! Schema::hasColumn('struk_settings', 'mode_template')) {
                $table->string('mode_template', 20)->default('global')->after('show_status');
            }
            if (! Schema::hasColumn('struk_settings', 'nama_toko_admin')) {
                $table->string('nama_toko_admin', 120)->nullable()->after('mode_template');
            }
            if (! Schema::hasColumn('struk_settings', 'alamat_toko_admin')) {
                $table->string('alamat_toko_admin', 255)->nullable()->after('nama_toko_admin');
            }
            if (! Schema::hasColumn('struk_settings', 'header_text_admin')) {
                $table->text('header_text_admin')->nullable()->after('alamat_toko_admin');
            }
            if (! Schema::hasColumn('struk_settings', 'footer_text_admin')) {
                $table->text('footer_text_admin')->nullable()->after('header_text_admin');
            }
            if (! Schema::hasColumn('struk_settings', 'nama_toko_kasir')) {
                $table->string('nama_toko_kasir', 120)->nullable()->after('footer_text_admin');
            }
            if (! Schema::hasColumn('struk_settings', 'alamat_toko_kasir')) {
                $table->string('alamat_toko_kasir', 255)->nullable()->after('nama_toko_kasir');
            }
            if (! Schema::hasColumn('struk_settings', 'header_text_kasir')) {
                $table->text('header_text_kasir')->nullable()->after('alamat_toko_kasir');
            }
            if (! Schema::hasColumn('struk_settings', 'footer_text_kasir')) {
                $table->text('footer_text_kasir')->nullable()->after('header_text_kasir');
            }
            if (! Schema::hasColumn('struk_settings', 'nama_cabang')) {
                $table->string('nama_cabang', 120)->nullable()->after('footer_text_kasir');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('struk_settings')) {
            return;
        }

        Schema::table('struk_settings', function (Blueprint $table): void {
            foreach ([
                'logo_max_width',
                'show_kode_nota',
                'show_id_pesanan',
                'show_waktu',
                'show_pelanggan',
                'show_kasir',
                'show_metode',
                'show_status',
                'mode_template',
                'nama_toko_admin',
                'alamat_toko_admin',
                'header_text_admin',
                'footer_text_admin',
                'nama_toko_kasir',
                'alamat_toko_kasir',
                'header_text_kasir',
                'footer_text_kasir',
                'nama_cabang',
            ] as $column) {
                if (Schema::hasColumn('struk_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

