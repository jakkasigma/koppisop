<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('absensi')) {
            return;
        }

        Schema::table('absensi', function (Blueprint $table): void {
            if (! Schema::hasColumn('absensi', 'checkout_correction_status')) {
                $table->string('checkout_correction_status', 30)->nullable()->after('verified_at');
            }
            if (! Schema::hasColumn('absensi', 'checkout_requested_pulang')) {
                $table->dateTime('checkout_requested_pulang')->nullable()->after('checkout_correction_status');
            }
            if (! Schema::hasColumn('absensi', 'checkout_requested_at')) {
                $table->dateTime('checkout_requested_at')->nullable()->after('checkout_requested_pulang');
            }
            if (! Schema::hasColumn('absensi', 'checkout_request_note')) {
                $table->text('checkout_request_note')->nullable()->after('checkout_requested_at');
            }
            if (! Schema::hasColumn('absensi', 'checkout_review_note')) {
                $table->text('checkout_review_note')->nullable()->after('checkout_request_note');
            }
            if (! Schema::hasColumn('absensi', 'checkout_reviewed_by')) {
                $table->unsignedBigInteger('checkout_reviewed_by')->nullable()->after('checkout_review_note');
            }
            if (! Schema::hasColumn('absensi', 'checkout_reviewed_at')) {
                $table->dateTime('checkout_reviewed_at')->nullable()->after('checkout_reviewed_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('absensi')) {
            return;
        }

        Schema::table('absensi', function (Blueprint $table): void {
            $dropColumns = array_filter([
                Schema::hasColumn('absensi', 'checkout_correction_status') ? 'checkout_correction_status' : null,
                Schema::hasColumn('absensi', 'checkout_requested_pulang') ? 'checkout_requested_pulang' : null,
                Schema::hasColumn('absensi', 'checkout_requested_at') ? 'checkout_requested_at' : null,
                Schema::hasColumn('absensi', 'checkout_request_note') ? 'checkout_request_note' : null,
                Schema::hasColumn('absensi', 'checkout_review_note') ? 'checkout_review_note' : null,
                Schema::hasColumn('absensi', 'checkout_reviewed_by') ? 'checkout_reviewed_by' : null,
                Schema::hasColumn('absensi', 'checkout_reviewed_at') ? 'checkout_reviewed_at' : null,
            ]);

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
