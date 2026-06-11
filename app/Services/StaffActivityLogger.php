<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\StaffActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class StaffActivityLogger
{
    public function log(
        Request $request,
        Karyawan|int|null $staff,
        string $actionKey,
        string $actionLabel,
        string $summary,
        array $meta = [],
        ?string $targetType = null,
        int|string|null $targetId = null,
        ?string $targetLabel = null,
    ): void {
        if (! Schema::hasTable('staff_activity_logs')) {
            return;
        }

        $staffModel = $staff instanceof Karyawan
            ? $staff
            : (($staff && (int) $staff > 0)
                ? Karyawan::query()->where('id_karyawan', (int) $staff)->first([
                    'id_karyawan',
                    'nama_karyawan',
                    'jabatan',
                    'employment_type',
                ])
                : null);

        StaffActivityLog::query()->create([
            'karyawan_id' => (int) ($staffModel?->id_karyawan ?? 0) ?: null,
            'actor_name' => trim((string) ($staffModel?->nama_karyawan ?? '')) ?: null,
            'actor_role' => trim((string) ($staffModel?->jabatan ?? '')) ?: null,
            'employment_type' => $staffModel ? $staffModel->employmentTypeValue() : null,
            'action_key' => $actionKey,
            'action_label' => $actionLabel,
            'summary' => $summary,
            'target_type' => $targetType,
            'target_label' => $targetLabel,
            'target_id' => is_numeric($targetId) ? (int) $targetId : null,
            'meta' => $meta !== [] ? $meta : null,
            'ip_address' => $request->ip(),
            'user_agent' => trim((string) $request->userAgent()) ?: null,
        ]);
    }
}
