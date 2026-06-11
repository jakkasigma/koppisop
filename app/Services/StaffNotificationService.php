<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\JadwalTukarRequest;
use App\Models\Karyawan;
use App\Models\LeaveRequest;
use App\Models\PayrollSlip;
use App\Models\StaffNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class StaffNotificationService
{
    public function notify(
        Karyawan|int $staff,
        string $category,
        string $title,
        ?string $body = null,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
        ?string $eventKey = null,
        array $meta = [],
        ?Carbon $createdAt = null,
        ?Carbon $readAt = null,
    ): ?StaffNotification {
        if (! Schema::hasTable('staff_notifications')) {
            return null;
        }

        $staffId = $staff instanceof Karyawan ? (int) $staff->id_karyawan : (int) $staff;
        if ($staffId <= 0) {
            return null;
        }

        $notification = $eventKey
            ? StaffNotification::query()->firstOrNew([
                'id_karyawan' => $staffId,
                'event_key' => $eventKey,
            ])
            : new StaffNotification();

        $isNew = ! $notification->exists;

        $notification->fill([
            'id_karyawan' => $staffId,
            'category' => $category,
            'title' => trim($title),
            'body' => trim((string) $body) !== '' ? trim((string) $body) : null,
            'action_url' => trim((string) $actionUrl) !== '' ? trim((string) $actionUrl) : null,
            'action_label' => trim((string) $actionLabel) !== '' ? trim((string) $actionLabel) : null,
            'event_key' => $eventKey !== null && trim($eventKey) !== '' ? trim($eventKey) : null,
            'meta' => $meta !== [] ? $meta : null,
        ]);

        if ($readAt instanceof Carbon) {
            $notification->read_at = $readAt;
        } elseif ($isNew) {
            $notification->read_at = null;
        }

        if ($isNew && $createdAt instanceof Carbon) {
            $notification->created_at = $createdAt;
            $notification->updated_at = $createdAt;
        }

        $notification->save();

        return $notification;
    }

    public function markAsRead(StaffNotification $notification): void
    {
        if (! $notification->read_at) {
            $notification->forceFill(['read_at' => now()])->save();
        }
    }

    public function unreadCount(int $staffId): int
    {
        if (! Schema::hasTable('staff_notifications')) {
            return 0;
        }

        return (int) StaffNotification::query()
            ->where('id_karyawan', $staffId)
            ->whereNull('read_at')
            ->count();
    }

    public function backfillHistorical(): int
    {
        if (! Schema::hasTable('staff_notifications')) {
            return 0;
        }

        $count = 0;

        if (Schema::hasTable('payroll_slips')) {
            PayrollSlip::query()
                ->with('karyawan')
                ->where('status', PayrollSlip::STATUS_FINALIZED)
                ->orderBy('period_month')
                ->chunk(100, function ($slips) use (&$count): void {
                    foreach ($slips as $slip) {
                        $createdAt = $slip->finalized_at ?? $slip->generated_at ?? $slip->created_at ?? now();
                        $periodLabel = $slip->periodLabel();
                        $result = $this->notify(
                            (int) $slip->id_karyawan,
                            StaffNotification::CATEGORY_PAYROLL,
                            'Slip gaji ' . $periodLabel . ' sudah tersedia',
                            'Gaji bulan ' . $periodLabel . ' sudah difinalkan admin. Total diterima Rp ' . number_format((int) $slip->net_amount, 0, ',', '.') . '.',
                            route('staff.payroll.show', ['payrollSlip' => $slip->getKey()]),
                            'Lihat slip',
                            'payroll-finalized:' . (int) $slip->getKey(),
                            [
                                'type' => 'payroll',
                                'payroll_slip_id' => (int) $slip->getKey(),
                            ],
                            $createdAt instanceof Carbon ? $createdAt : Carbon::parse($createdAt)
                        );

                        if ($result && $result->wasRecentlyCreated) {
                            $count++;
                        }
                    }
                });
        }

        if (Schema::hasTable('leave_requests')) {
            LeaveRequest::query()
                ->whereIn('status', ['approved', 'rejected'])
                ->whereNotNull('approved_at')
                ->orderBy('approved_at')
                ->chunk(100, function ($rows) use (&$count): void {
                    foreach ($rows as $leave) {
                        $statusLabel = $leave->status === 'approved' ? 'disetujui' : 'ditolak';
                        $jenis = trim((string) ($leave->jenis ?? 'izin'));
                        $body = ucfirst($jenis) . ' untuk ' . $leave->tanggal_awal?->format('d M Y');
                        if ($leave->tanggal_akhir && $leave->tanggal_akhir->ne($leave->tanggal_awal)) {
                            $body .= ' s/d ' . $leave->tanggal_akhir->format('d M Y');
                        }
                        if (trim((string) ($leave->note ?? '')) !== '') {
                            $body .= '. Catatan admin: ' . trim((string) $leave->note);
                        }

                        $result = $this->notify(
                            (int) $leave->id_karyawan,
                            StaffNotification::CATEGORY_LEAVE,
                            ucfirst($jenis) . ' ' . $statusLabel,
                            $body . '.',
                            route('staff.leave.index'),
                            'Lihat pengajuan',
                            'leave-status:' . (int) $leave->id . ':' . (string) $leave->status,
                            [
                                'type' => 'leave',
                                'leave_id' => (int) $leave->id,
                                'status' => (string) $leave->status,
                            ],
                            $leave->approved_at instanceof Carbon ? $leave->approved_at : Carbon::parse($leave->approved_at)
                        );

                        if ($result && $result->wasRecentlyCreated) {
                            $count++;
                        }
                    }
                });
        }

        if (Schema::hasTable('jadwal_tukar_requests')) {
            JadwalTukarRequest::query()
                ->with(['fromKaryawan', 'toKaryawan'])
                ->whereIn('status', ['approved', 'rejected'])
                ->whereNotNull('approved_at')
                ->orderBy('approved_at')
                ->chunk(100, function ($rows) use (&$count): void {
                    foreach ($rows as $swap) {
                        $statusLabel = $swap->status === 'approved' ? 'disetujui' : 'ditolak';
                        $fromName = trim((string) ($swap->fromKaryawan?->nama_karyawan ?? 'staff asal'));
                        $toName = trim((string) ($swap->toKaryawan?->nama_karyawan ?? 'staff tujuan'));
                        $body = 'Pengajuan tukar shift antara ' . $fromName . ' dan ' . $toName . ' ' . $statusLabel . '.';
                        if (trim((string) ($swap->note ?? '')) !== '') {
                            $body .= ' Catatan admin: ' . trim((string) $swap->note);
                        }

                        foreach (array_unique([(int) $swap->from_karyawan_id, (int) $swap->to_karyawan_id]) as $staffId) {
                            if ($staffId <= 0) {
                                continue;
                            }

                            $result = $this->notify(
                                $staffId,
                                StaffNotification::CATEGORY_SWAP,
                                'Permintaan tukar shift ' . $statusLabel,
                                $body,
                                route('staff.swap.index'),
                                'Lihat swap',
                                'swap-status:' . (int) $swap->id . ':' . $staffId . ':' . (string) $swap->status,
                                [
                                    'type' => 'swap',
                                    'swap_id' => (int) $swap->id,
                                    'status' => (string) $swap->status,
                                ],
                                $swap->approved_at instanceof Carbon ? $swap->approved_at : Carbon::parse($swap->approved_at)
                            );

                            if ($result && $result->wasRecentlyCreated) {
                                $count++;
                            }
                        }
                    }
                });
        }

        if (Schema::hasTable('absensi')) {
            Absensi::query()
                ->whereIn('checkout_correction_status', [
                    Absensi::CHECKOUT_CORRECTION_APPROVED,
                    Absensi::CHECKOUT_CORRECTION_REJECTED,
                    Absensi::CHECKOUT_CORRECTION_MANUAL,
                ])
                ->whereNotNull('checkout_reviewed_at')
                ->orderBy('checkout_reviewed_at')
                ->chunk(100, function ($rows) use (&$count): void {
                    foreach ($rows as $absensi) {
                        $title = match ((string) $absensi->checkout_correction_status) {
                            Absensi::CHECKOUT_CORRECTION_APPROVED => 'Koreksi absen pulang disetujui',
                            Absensi::CHECKOUT_CORRECTION_REJECTED => 'Koreksi absen pulang ditolak',
                            default => 'Jam pulang manual ditambahkan admin',
                        };

                        $body = 'Absensi tanggal ' . $absensi->tanggal?->format('d M Y') . ' sudah diperbarui admin.';
                        if (trim((string) ($absensi->checkout_review_note ?? '')) !== '') {
                            $body .= ' Catatan: ' . trim((string) $absensi->checkout_review_note);
                        }

                        $createdAt = $absensi->checkout_reviewed_at instanceof Carbon
                            ? $absensi->checkout_reviewed_at
                            : Carbon::parse($absensi->checkout_reviewed_at);

                        $result = $this->notify(
                            (int) $absensi->id_karyawan,
                            StaffNotification::CATEGORY_ATTENDANCE,
                            $title,
                            $body,
                            route('absen.form'),
                            'Buka absen',
                            'attendance-correction:' . (int) $absensi->id_absensi . ':' . (string) $absensi->checkout_correction_status,
                            [
                                'type' => 'attendance',
                                'absensi_id' => (int) $absensi->id_absensi,
                                'status' => (string) $absensi->checkout_correction_status,
                            ],
                            $createdAt
                        );

                        if ($result && $result->wasRecentlyCreated) {
                            $count++;
                        }
                    }
                });
        }

        return $count;
    }

    public function seedDemoNotifications(?int $staffId = null): int
    {
        if (! Schema::hasTable('staff_notifications')) {
            return 0;
        }

        $targets = Karyawan::query()
            ->when($staffId !== null, fn ($q) => $q->where('id_karyawan', $staffId))
            ->when($staffId === null, fn ($q) => $q->where('is_active', 1)->orderBy('nama_karyawan'))
            ->get(['id_karyawan', 'nama_karyawan']);

        $count = 0;

        foreach ($targets as $staff) {
            $baseDate = now()->startOfDay();

            $items = [
                [
                    'category' => StaffNotification::CATEGORY_PAYROLL,
                    'title' => 'Slip gaji April 2026 siap dicek',
                    'body' => 'Admin sudah menyiapkan slip gaji bulan April 2026. Cek rincian pendapatan dan potongannya.',
                    'action_url' => route('staff.payroll.index'),
                    'action_label' => 'Buka slip',
                    'event_key' => 'demo-payroll:' . (int) $staff->id_karyawan . ':2026-04',
                    'created_at' => $baseDate->copy()->subDays(1)->setTime(8, 30),
                    'read_at' => null,
                ],
                [
                    'category' => StaffNotification::CATEGORY_SWAP,
                    'title' => 'Permintaan tukar shift disetujui',
                    'body' => 'Swap shift untuk akhir pekan sudah disetujui admin. Pastikan cek jadwal terbaru kamu.',
                    'action_url' => route('staff.swap.index'),
                    'action_label' => 'Lihat swap',
                    'event_key' => 'demo-swap:' . (int) $staff->id_karyawan,
                    'created_at' => $baseDate->copy()->subDays(2)->setTime(16, 10),
                    'read_at' => null,
                ],
                [
                    'category' => StaffNotification::CATEGORY_LEAVE,
                    'title' => 'Pengajuan izin ditolak',
                    'body' => 'Pengajuan izin belum bisa disetujui. Silakan cek catatan admin dan ajukan ulang jika perlu.',
                    'action_url' => route('staff.leave.index'),
                    'action_label' => 'Buka izin',
                    'event_key' => 'demo-leave:' . (int) $staff->id_karyawan,
                    'created_at' => $baseDate->copy()->subDays(4)->setTime(10, 5),
                    'read_at' => $baseDate->copy()->subDays(3)->setTime(9, 0),
                ],
            ];

            foreach ($items as $item) {
                $result = $this->notify(
                    (int) $staff->id_karyawan,
                    $item['category'],
                    $item['title'],
                    $item['body'],
                    $item['action_url'],
                    $item['action_label'],
                    $item['event_key'],
                    ['type' => 'demo'],
                    $item['created_at'],
                    $item['read_at']
                );

                if ($result && $result->wasRecentlyCreated) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
