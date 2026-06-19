<?php $__env->startSection('title', 'Kelola Slip Gaji'); ?>

<?php $__env->startSection('content'); ?>
<div class="container admin-form-page">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Slip Builder</div>
            <h1>Kelola Slip Gaji</h1>
            <p><?php echo e($karyawan->nama_karyawan); ?> &middot; <?php echo e($periodLabel); ?> &middot; <?php echo e($karyawan->employmentTypeLabel()); ?></p>
        </div>
        <div class="admin-page-actions">
            <a class="admin-chip" href="<?php echo e(route('dashboard.payroll.index', ['bulan' => $periodKey])); ?>">Kembali ke daftar</a>
            <?php if($slip): ?>
                <a class="admin-chip soft" href="<?php echo e(route('dashboard.payroll.print', ['payrollSlip' => $slip, 'autoprint' => 1])); ?>" target="_blank" rel="noopener">Cetak Slip</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="payroll-person-card panel">
        <div class="payroll-person-main">
            <div class="payroll-person-avatar"><?php echo e(\Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $karyawan->nama_karyawan, 0, 1))); ?></div>
            <div class="payroll-person-copy">
                <strong><?php echo e($karyawan->nama_karyawan); ?></strong>
                <span><?php echo e($karyawan->jabatan ?: 'Staf Operasional'); ?></span>
                <div class="payroll-person-meta">
                    <span class="pill gray"><?php echo e($karyawan->employmentTypeLabel()); ?></span>
                    <span class="pill neu"><?php echo e($summary['salary_scheme_label']); ?></span>
                    <span class="pill <?php echo e(($slip?->status ?? '') === \App\Models\PayrollSlip::STATUS_FINALIZED ? 'ok' : 'gray'); ?>"><?php echo e($slip?->statusLabel() ?? 'Belum dibuat'); ?></span>
                </div>
            </div>
        </div>
        <div class="payroll-person-side">
            <div class="payroll-person-stat">
                <span>Periode</span>
                <strong><?php echo e($periodLabel); ?></strong>
            </div>
            <div class="payroll-person-stat">
                <span>Gaji Bersih Saat Ini</span>
                <strong>Rp <?php echo e(number_format((int) ($slip->net_amount ?? $summary['gross_amount'] - $summary['auto_deduction_amount']), 0, ',', '.')); ?></strong>
            </div>
        </div>
    </div>

    <?php if(session('success')): ?>
        <div class="alert ok"><?php echo e(session('success')); ?></div>
    <?php endif; ?>
    <?php if($errors->any()): ?>
        <div class="alert err"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($error); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
    <?php endif; ?>

    <div class="kpi-grid payroll-kpi-grid">
        <div class="kpi">
            <div class="label">Skema Gaji</div>
            <div class="value"><?php echo e($summary['salary_scheme_label']); ?></div>
            <div class="sub"><?php echo e($karyawan->baseSalaryLabel()); ?></div>
        </div>
        <div class="kpi gray">
            <div class="label">Jam Dibayar</div>
            <div class="value"><?php echo e($summary['paid_hours_label']); ?></div>
            <div class="sub">Reguler <?php echo e($summary['regular_paid_hours_label']); ?> <?php if($summary['overtime_minutes'] > 0): ?>&middot; Lembur <?php echo e($summary['overtime_hours_label']); ?><?php endif; ?></div>
        </div>
        <div class="kpi blue">
            <div class="label">Gaji Kotor</div>
            <div class="value">Rp <?php echo e(number_format((int) $summary['gross_amount'], 0, ',', '.')); ?></div>
            <div class="sub">Dasar Rp <?php echo e(number_format((int) $summary['base_amount'], 0, ',', '.')); ?> <?php if($summary['overtime_amount'] > 0): ?>&middot; Lembur Rp <?php echo e(number_format((int) $summary['overtime_amount'], 0, ',', '.')); ?><?php endif; ?></div>
        </div>
        <div class="kpi <?php echo e(($slip?->status ?? '') === \App\Models\PayrollSlip::STATUS_FINALIZED ? '' : 'gray'); ?>">
            <div class="label">Status Slip</div>
            <div class="value"><?php echo e($slip?->statusLabel() ?? 'Belum dibuat'); ?></div>
            <div class="sub"><?php echo e($slip?->updated_at?->format('d M Y H:i') ?? 'Belum pernah disimpan admin.'); ?></div>
        </div>
    </div>

    <div class="payroll-detail-grid">
        <div class="panel">
            <div class="payroll-panel-head">
                <div>
                    <h2>Ringkasan Realtime</h2>
                    <p>Hitungan ini mengikuti tipe kerja, jadwal, dan absensi pada periode terpilih.</p>
                </div>
            </div>

            <div class="payroll-summary-list">
                <div class="summary-row"><span>Shift terjadwal</span><strong><?php echo e($summary['scheduled_shift_count']); ?> shift</strong></div>
                <div class="summary-row"><span>Shift hadir</span><strong><?php echo e($summary['present_shift_count']); ?> shift</strong></div>
                <?php if(($summary['approved_leave_shift_count'] ?? 0) > 0): ?>
                    <div class="summary-row"><span>Izin / sakit disetujui</span><strong><?php echo e($summary['approved_leave_shift_count']); ?> shift &middot; <?php echo e($summary['approved_leave_day_count'] ?? 0); ?> hari</strong></div>
                <?php endif; ?>
                <div class="summary-row"><span>Shift alpa</span><strong><?php echo e($summary['alpha_shift_count']); ?> shift <?php if(($summary['alpha_day_count'] ?? 0) > 0): ?>&middot; <?php echo e($summary['alpha_day_count']); ?> hari <?php endif; ?></strong></div>
                <div class="summary-row"><span>Telat tercatat</span><strong><?php echo e($summary['late_count']); ?> kali &middot; <?php echo e($summary['late_minutes']); ?> menit</strong></div>
                <div class="summary-row"><span>Pulang cepat</span><strong><?php echo e($summary['early_leave_count']); ?> kali &middot; <?php echo e($summary['early_leave_hours_label']); ?></strong></div>
                <div class="summary-row"><span>Hari hadir tercatat</span><strong><?php echo e($summary['present_day_count']); ?> hari</strong></div>
                <div class="summary-row"><span>Jam reguler</span><strong><?php echo e($summary['regular_paid_hours_label']); ?></strong></div>
                <div class="summary-row"><span>Jam lembur</span><strong><?php echo e($summary['overtime_hours_label']); ?></strong></div>
                <?php if($summary['salary_scheme'] === 'hourly'): ?>
                    <div class="summary-row"><span>Tarif per jam</span><strong>Rp <?php echo e(number_format((int) $summary['hourly_rate'], 0, ',', '.')); ?></strong></div>
                <?php else: ?>
                    <div class="summary-row"><span>Gaji bulanan</span><strong>Rp <?php echo e(number_format((int) $summary['monthly_salary'], 0, ',', '.')); ?></strong></div>
                <?php endif; ?>
                <div class="summary-row grand"><span>Gaji dasar</span><strong>Rp <?php echo e(number_format((int) $summary['base_amount'], 0, ',', '.')); ?></strong></div>
                <div class="summary-row"><span>Tarif lembur</span><strong>Rp <?php echo e(number_format((int) $summary['overtime_rate'], 0, ',', '.')); ?> / jam</strong></div>
                <div class="summary-row"><span>Bayar lembur</span><strong>Rp <?php echo e(number_format((int) $summary['overtime_amount'], 0, ',', '.')); ?></strong></div>
                <div class="summary-row"><span>Potongan alpa otomatis</span><strong>Rp <?php echo e(number_format((int) $summary['auto_alpha_deduction'], 0, ',', '.')); ?></strong></div>
                <div class="summary-row"><span>Potongan izin / sakit approved</span><strong>Rp <?php echo e(number_format((int) ($summary['auto_approved_leave_deduction'] ?? 0), 0, ',', '.')); ?></strong></div>
                <div class="summary-row"><span>Potongan telat otomatis</span><strong>Rp <?php echo e(number_format((int) $summary['auto_late_deduction'], 0, ',', '.')); ?></strong></div>
                <div class="summary-row"><span>Potongan pulang cepat otomatis</span><strong>Rp <?php echo e(number_format((int) $summary['auto_early_leave_deduction'], 0, ',', '.')); ?></strong></div>
                <div class="summary-row grand"><span>Potongan otomatis total</span><strong>Rp <?php echo e(number_format((int) $summary['auto_deduction_amount'], 0, ',', '.')); ?></strong></div>
            </div>

            <div class="payroll-policy-note">
                <strong>Aturan aktif</strong>
                <span>FT alpa Rp <?php echo e(number_format((int) ($summary['policy']['alpha_deduction_unit_amount'] ?? 0), 0, ',', '.')); ?> / <?php echo e($summary['policy']['alpha_deduction_unit_label'] ?? 'hari kerja'); ?></span>
                <span>FT izin/sakit approved Rp <?php echo e(number_format((int) ($summary['policy']['approved_leave_deduction_unit_amount'] ?? 0), 0, ',', '.')); ?> / <?php echo e($summary['policy']['approved_leave_deduction_unit_label'] ?? 'hari kerja'); ?></span>
                <span>Perhitungan FT: gaji bulanan &divide; jumlah hari kerja terjadwal di bulan ini</span>
                <span>PT alpa Rp <?php echo e(number_format((int) ($policy['alpha_part_time'] ?? 0), 0, ',', '.')); ?> / shift</span>
                <span>Telat Rp <?php echo e(number_format((int) ($policy['late_per_minute'] ?? 0), 0, ',', '.')); ?> / menit</span>
                <span>Pulang cepat FT ikut tarif telat per menit</span>
                <span>Lembur FT Rp <?php echo e(number_format((int) ($policy['overtime_full_time'] ?? 0), 0, ',', '.')); ?> / jam</span>
            </div>
        </div>

        <div class="panel payroll-form-panel">
            <div class="payroll-panel-head">
                <div>
                    <h2>Simpan Slip</h2>
                    <p>Tambahkan bonus, potongan, lalu simpan sebagai draft atau final.</p>
                </div>
            </div>

            <form method="post" action="<?php echo e(route('dashboard.payroll.store', $karyawan)); ?>" class="form-grid">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="bulan" value="<?php echo e($periodKey); ?>">
                <div class="field">
                    <label>Bonus</label>
                    <input type="number" min="0" step="1000" name="bonus_amount" value="<?php echo e(old('bonus_amount', (int) ($slip->bonus_amount ?? 0))); ?>">
                    <div class="hint">Tambahan manual seperti insentif atau bonus target.</div>
                </div>
                <div class="field">
                    <label>Potongan Manual</label>
                    <input type="number" min="0" step="1000" name="deduction_amount" value="<?php echo e(old('deduction_amount', (int) ($slip->deduction_amount ?? 0))); ?>">
                    <div class="hint">Potongan otomatis alpa/telat sudah dihitung terpisah di atas.</div>
                </div>
                <div class="field">
                    <label>Status Slip</label>
                    <select name="status" required>
                        <?php $__currentLoopData = $statusOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($value); ?>" <?php if(old('status', $slip->status ?? \App\Models\PayrollSlip::STATUS_DRAFT) === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="field full">
                    <label>Catatan Slip</label>
                    <textarea name="notes" rows="4" placeholder="Contoh: bonus event weekend, potongan kasbon, dll."><?php echo e(old('notes', $slip->notes ?? '')); ?></textarea>
                </div>
                <?php
                    $previewBonus = (int) old('bonus_amount', (int) ($slip->bonus_amount ?? 0));
                    $previewDeduction = (int) old('deduction_amount', (int) ($slip->deduction_amount ?? 0));
                    $previewNet = max(0, (int) $summary['gross_amount'] + $previewBonus - $previewDeduction - (int) $summary['auto_deduction_amount']);
                ?>
                <div class="payroll-net-preview full">
                    <span>Potongan otomatis alpa + telat + pulang cepat</span>
                    <strong>Rp <?php echo e(number_format((int) $summary['auto_deduction_amount'], 0, ',', '.')); ?></strong>
                </div>
                <div class="payroll-net-preview full">
                    <span>Estimasi gaji bersih saat disimpan</span>
                    <strong>Rp <?php echo e(number_format($previewNet, 0, ',', '.')); ?></strong>
                </div>
                <div class="actions full">
                    <button class="btn-primary" type="submit">Simpan Slip</button>
                    <a class="btn-neutral" href="<?php echo e(route('dashboard.payroll.index', ['bulan' => $periodKey])); ?>">Kembali</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\psrnl\laravel\kasir\resources\views/dashboard/payroll/show.blade.php ENDPATH**/ ?>