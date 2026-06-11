<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('struk_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('struk_settings', 'enable_payment_shopeefood')) {
                $table->boolean('enable_payment_shopeefood')->default(false)->after('enable_payment_debit');
            }
            if (! Schema::hasColumn('struk_settings', 'enable_payment_gofood')) {
                $table->boolean('enable_payment_gofood')->default(false)->after('enable_payment_shopeefood');
            }
            if (! Schema::hasColumn('struk_settings', 'enable_payment_grabfood')) {
                $table->boolean('enable_payment_grabfood')->default(false)->after('enable_payment_gofood');
            }
        });
    }

    public function down(): void
    {
        Schema::table('struk_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('struk_settings', 'enable_payment_grabfood')) {
                $table->dropColumn('enable_payment_grabfood');
            }
            if (Schema::hasColumn('struk_settings', 'enable_payment_gofood')) {
                $table->dropColumn('enable_payment_gofood');
            }
            if (Schema::hasColumn('struk_settings', 'enable_payment_shopeefood')) {
                $table->dropColumn('enable_payment_shopeefood');
            }
        });
    }
};
