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
            if (! Schema::hasColumn('struk_settings', 'enable_payment_cash')) {
                $table->boolean('enable_payment_cash')->default(true)->after('operasional_reset_hour');
            }
            if (! Schema::hasColumn('struk_settings', 'enable_payment_qris')) {
                $table->boolean('enable_payment_qris')->default(true)->after('enable_payment_cash');
            }
            if (! Schema::hasColumn('struk_settings', 'enable_payment_debit')) {
                $table->boolean('enable_payment_debit')->default(true)->after('enable_payment_qris');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('struk_settings')) {
            return;
        }

        Schema::table('struk_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('struk_settings', 'enable_payment_debit')) {
                $table->dropColumn('enable_payment_debit');
            }
            if (Schema::hasColumn('struk_settings', 'enable_payment_qris')) {
                $table->dropColumn('enable_payment_qris');
            }
            if (Schema::hasColumn('struk_settings', 'enable_payment_cash')) {
                $table->dropColumn('enable_payment_cash');
            }
        });
    }
};
