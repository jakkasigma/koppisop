<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\StrukSetting;
use Illuminate\Support\Carbon;

class AbsensiCorrectionService
{
    public function graceMinutes(): int
    {
        return 240;
    }

    public function expectedCheckoutAt(Absensi $absensi, StrukSetting $setting, ?Karyawan $karyawan = null): ?Carbon
    {
        $employmentType = $karyawan?->employment_type;
        if (! $employmentType && $absensi->relationLoaded('karyawan')) {
            $employmentType = $absensi->karyawan?->employment_type;
        }

        $durationMinutes = Karyawan::employmentDurationMinutesFor($employmentType);
        $date = $absensi->tanggal?->format('Y-m-d');
        $shiftNo = (int) ($absensi->shift_no ?? 0);

        if ($date && $shiftNo > 0) {
            $range = $setting->shiftRangeFor($shiftNo, $employmentType, $date);
            $start = trim((string) ($range['start'] ?? ''));
            if ($start !== '' && $start !== '-') {
                return Carbon::parse($date . ' ' . $start . ':00')->addMinutes($durationMinutes);
            }
        }

        if ($absensi->waktu_masuk) {
            return $absensi->waktu_masuk->copy()->addMinutes($durationMinutes);
        }

        return null;
    }

    public function deadlineAt(Absensi $absensi): Carbon
    {
        $date = $absensi->tanggal?->copy() ?? now()->startOfDay();

        return $date->addDay()->endOfDay();
    }

    public function state(
        Absensi $absensi,
        StrukSetting $setting,
        ?Karyawan $karyawan = null,
        ?Carbon $now = null,
    ): array {
        $now ??= now();
        $hasClockIn = (bool) $absensi->waktu_masuk;
        $hasClockOut = (bool) $absensi->waktu_pulang;
        $deadlineAt = $this->deadlineAt($absensi);
        $expectedCheckoutAt = $this->expectedCheckoutAt($absensi, $setting, $karyawan);
        $forgotAt = $expectedCheckoutAt?->copy()->addMinutes($this->graceMinutes());
        $status = (string) ($absensi->checkout_correction_status ?? '');
        $canRequest = false;
        $key = 'complete';
        $label = 'Lengkap';
        $class = 'ok';
        $note = 'Data masuk dan pulang harian sudah lengkap.';

        if ($hasClockIn && ! $hasClockOut) {
            if ($status === Absensi::CHECKOUT_CORRECTION_REQUESTED) {
                $key = 'requested';
                $label = 'Menunggu Koreksi Admin';
                $class = 'warn';
                $note = 'Usulan jam pulang sudah dikirim dan sedang menunggu keputusan admin.';
            } elseif ($status === Absensi::CHECKOUT_CORRECTION_REJECTED) {
                $key = 'rejected';
                $label = 'Koreksi Ditolak';
                $class = 'bad';
                $note = $now->lte($deadlineAt)
                    ? 'Usulan jam pulang sebelumnya ditolak. Kamu masih bisa ajukan ulang sampai ' . $deadlineAt->translatedFormat('d M Y, H:i') . '.'
                    : 'Usulan jam pulang sebelumnya ditolak dan batas koreksi mandiri sudah lewat. Hubungi admin.';
                $canRequest = $now->lte($deadlineAt);
            } elseif ($forgotAt && $now->gt($forgotAt)) {
                if ($now->gt($deadlineAt)) {
                    $key = 'expired';
                    $label = 'Perlu Koreksi Admin';
                    $class = 'bad';
                    $note = 'Batas ajukan koreksi mandiri sudah lewat. Admin perlu mengisi jam pulang secara manual.';
                } else {
                    $key = 'forgot';
                    $label = 'Lupa Absen Pulang';
                    $class = 'warn';
                    $note = 'Ajukan koreksi jam pulang paling lambat ' . $deadlineAt->translatedFormat('d M Y, H:i') . '.';
                    $canRequest = true;
                }
            } else {
                $key = 'waiting_checkout';
                $label = 'Menunggu Pulang';
                $class = 'off';
                $note = $expectedCheckoutAt
                    ? 'Jam pulang normal sekitar ' . $expectedCheckoutAt->format('H:i') . '. Setelah itu, data siap dilengkapi.'
                    : 'Absen pulang belum tercatat.';
            }
        }

        return [
            'key' => $key,
            'label' => $label,
            'class' => $class,
            'note' => $note,
            'can_request' => $canRequest,
            'deadline_at' => $deadlineAt,
            'deadline_label' => $deadlineAt->translatedFormat('d M Y, H:i'),
            'expected_checkout_at' => $expectedCheckoutAt,
            'expected_checkout_label' => $expectedCheckoutAt?->format('d M Y, H:i') ?? '-',
            'expected_checkout_input' => $expectedCheckoutAt?->format('Y-m-d\TH:i') ?? '',
            'requested_pulang_label' => $absensi->checkout_requested_pulang?->translatedFormat('d M Y, H:i') ?? '-',
            'requested_pulang_input' => $absensi->checkout_requested_pulang?->format('Y-m-d\TH:i') ?? '',
            'requested_note' => (string) ($absensi->checkout_request_note ?? ''),
            'review_note' => (string) ($absensi->checkout_review_note ?? ''),
        ];
    }

    public function matchesFilter(array $state, string $filter): bool
    {
        $key = (string) ($state['key'] ?? 'complete');

        return match ($filter) {
            '', 'all' => true,
            'needs_attention' => in_array($key, ['requested', 'forgot', 'expired', 'rejected'], true),
            'requested' => $key === 'requested',
            'forgot' => in_array($key, ['forgot', 'expired'], true),
            'rejected' => $key === 'rejected',
            'waiting_checkout' => $key === 'waiting_checkout',
            default => true,
        };
    }
}
