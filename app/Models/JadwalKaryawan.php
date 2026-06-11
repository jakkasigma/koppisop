<?php

namespace App\Models;

use App\Models\Absensi;
use App\Models\KasirShiftSession;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalKaryawan extends Model
{
    protected $table = 'jadwal_karyawan';

    public $timestamps = false;

    protected $fillable = [
        'tanggal',
        'shift_ke',
        'id_karyawan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'shift_ke' => 'integer',
        'id_karyawan' => 'integer',
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'id_karyawan', 'id_karyawan');
    }

    public static function dashboardShiftSnapshot(): array
    {
        $today = now()->toDateString();
        $activeShift = KasirShiftSession::query()->active()->orderByDesc('started_at')->first();
        if (! $activeShift) {
            return [
                'hasActiveShift' => false,
                'shiftKe' => null,
                'today' => $today,
                'staff' => [],
            ];
        }

        $shiftKe = (int) ($activeShift->shift_ke ?? 0);
        $staffRows = self::query()
            ->with('karyawan')
            ->whereDate('tanggal', $today)
            ->where('shift_ke', $shiftKe)
            ->orderBy('id_karyawan')
            ->get();

        $ids = $staffRows->pluck('id_karyawan')->map(fn ($v) => (int) $v)->all();
        $absenSet = [];
        if (! empty($ids) && Schema::hasTable('absensi')) {
            $absenIds = Absensi::query()
                ->whereDate('tanggal', $today)
                ->whereIn('id_karyawan', $ids)
                ->whereNotNull('waktu_masuk')
                ->pluck('id_karyawan')
                ->map(fn ($v) => (int) $v)
                ->all();
            $absenSet = array_fill_keys($absenIds, true);
        }

        $staff = $staffRows->map(function ($r) use ($absenSet): array {
            return [
                'id_karyawan' => (int) $r->id_karyawan,
                'nama' => (string) ($r->karyawan?->nama_karyawan ?? '-'),
                'jabatan' => (string) ($r->karyawan?->jabatan ?? ''),
                'absen' => isset($absenSet[(int) $r->id_karyawan]),
            ];
        })->all();

        return [
            'hasActiveShift' => true,
            'shiftKe' => $shiftKe,
            'today' => $today,
            'staff' => $staff,
        ];
    }
}
