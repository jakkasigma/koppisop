<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jadwal_tukar_requests')) {
            return;
        }

        Schema::table('jadwal_tukar_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('jadwal_tukar_requests', 'staff_status')) {
                $table->string('staff_status', 20)->default('pending')->after('status'); // pending|approved|rejected
            }
            if (! Schema::hasColumn('jadwal_tukar_requests', 'staff_note')) {
                $table->text('staff_note')->nullable()->after('staff_status');
            }
            if (! Schema::hasColumn('jadwal_tukar_requests', 'staff_responded_by')) {
                $table->unsignedBigInteger('staff_responded_by')->nullable()->after('staff_note');
            }
            if (! Schema::hasColumn('jadwal_tukar_requests', 'staff_responded_at')) {
                $table->timestamp('staff_responded_at')->nullable()->after('staff_responded_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('jadwal_tukar_requests')) {
            return;
        }

        Schema::table('jadwal_tukar_requests', function (Blueprint $table): void {
            $columns = ['staff_status', 'staff_note', 'staff_responded_by', 'staff_responded_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('jadwal_tukar_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
