<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use App\Models\PayrollSlip;
use App\Models\StrukSetting;
use App\Services\PayrollCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StaffPayrollController extends Controller
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
    ) {
    }

    public function index(Request $request): View
    {
        $karyawan = $request->attributes->get('staff_karyawan');
        $setting = StrukSetting::current();
        $period = $this->calculator->resolvePeriodMonth((string) $request->query('bulan', now()->format('Y-m')));
        $rangeMonths = in_array((string) $request->query('rentang'), ['6', '12'], true)
            ? (int) $request->query('rentang')
            : 12;
        $liveSummary = $this->calculator->calculate($karyawan, $period);

        $currentSlip = PayrollSlip::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('period_month', $period->toDateString())
            ->first();

        $slips = PayrollSlip::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->orderByDesc('period_month')
            ->limit(12)
            ->get();

        $historySeries = $this->buildHistorySeries($slips, $period, $currentSlip, $liveSummary, $rangeMonths);
        $chart = $this->buildChartMeta($historySeries);
        $monthCards = $this->buildMonthCards($slips, $period, $currentSlip, $liveSummary);
        $nonZeroSeries = $historySeries->filter(fn (array $item) => (int) $item['amount'] > 0);
        $historyTotal = (int) $historySeries->sum('amount');
        $historyAverage = $nonZeroSeries->isNotEmpty()
            ? (int) round((float) $nonZeroSeries->avg('amount'))
            : 0;

        $seriesValues = $historySeries->values();
        $currentPoint = $seriesValues->last();
        $previousPoint = $seriesValues->count() > 1
            ? $seriesValues->get($seriesValues->count() - 2)
            : null;
        $deltaAmount = $previousPoint
            ? ((int) ($currentPoint['amount'] ?? 0) - (int) ($previousPoint['amount'] ?? 0))
            : null;

        return view('staff.payroll.index', [
            'karyawan' => $karyawan,
            'setting' => $setting,
            'period' => $period,
            'periodKey' => $period->format('Y-m'),
            'periodLabel' => $period->locale('id')->translatedFormat('F Y'),
            'rangeMonths' => $rangeMonths,
            'liveSummary' => $liveSummary,
            'currentSlip' => $currentSlip,
            'slips' => $slips,
            'historySeries' => $historySeries,
            'historyChart' => $chart,
            'historyTotal' => $historyTotal,
            'historyAverage' => $historyAverage,
            'savedSlipCount' => (int) $slips->count(),
            'monthCards' => $monthCards,
            'selectedAmount' => (int) (($currentSlip?->net_amount) ?? $liveSummary['estimated_net_amount']),
            'deltaAmount' => $deltaAmount,
            'deltaLabel' => $this->formatDeltaLabel($deltaAmount),
        ]);
    }

    public function show(Request $request, PayrollSlip $payrollSlip): View
    {
        $karyawan = $request->attributes->get('staff_karyawan');
        $setting = StrukSetting::current();

        abort_unless((int) $payrollSlip->id_karyawan === (int) $karyawan->id_karyawan, 404);

        $liveSummary = $this->calculator->calculate($karyawan, $payrollSlip->period_month);
        $previousSlip = PayrollSlip::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('period_month', '<', $payrollSlip->period_month->toDateString())
            ->orderByDesc('period_month')
            ->first();
        $nextSlip = PayrollSlip::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('period_month', '>', $payrollSlip->period_month->toDateString())
            ->orderBy('period_month')
            ->first();
        $compositionItems = $this->buildCompositionItems($payrollSlip);
        $compositionMax = max(1, (int) $compositionItems->max('amount'));
        $compareAmount = $previousSlip
            ? (int) $payrollSlip->net_amount - (int) $previousSlip->net_amount
            : null;

        return view('staff.payroll.show', [
            'karyawan' => $karyawan,
            'setting' => $setting,
            'payrollSlip' => $payrollSlip,
            'liveSummary' => $liveSummary,
            'previousSlip' => $previousSlip,
            'nextSlip' => $nextSlip,
            'compareAmount' => $compareAmount,
            'compareLabel' => $this->formatDeltaLabel($compareAmount),
            'compositionItems' => $compositionItems,
            'compositionMax' => $compositionMax,
        ]);
    }

    public function showPeriod(Request $request, string $period): View|\Illuminate\Http\RedirectResponse
    {
        $karyawan = $request->attributes->get('staff_karyawan');
        $setting = StrukSetting::current();
        $periodMonth = $this->calculator->resolvePeriodMonth($period);

        $savedSlip = PayrollSlip::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('period_month', $periodMonth->toDateString())
            ->first();

        if ($savedSlip) {
            return redirect()->route('staff.payroll.show', $savedSlip);
        }

        $liveSummary = $this->calculator->calculate($karyawan, $periodMonth);
        $previousSlip = PayrollSlip::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('period_month', '<', $periodMonth->toDateString())
            ->orderByDesc('period_month')
            ->first();
        $nextSlip = PayrollSlip::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('period_month', '>', $periodMonth->toDateString())
            ->orderBy('period_month')
            ->first();
        $compositionItems = $this->buildLiveCompositionItems($liveSummary);
        $compositionMax = max(1, (int) ($compositionItems->max('amount') ?? 0));
        $compareAmount = $previousSlip
            ? (int) $liveSummary['estimated_net_amount'] - (int) $previousSlip->net_amount
            : null;

        return view('staff.payroll.period', [
            'karyawan' => $karyawan,
            'setting' => $setting,
            'period' => $periodMonth,
            'liveSummary' => $liveSummary,
            'previousSlip' => $previousSlip,
            'nextSlip' => $nextSlip,
            'compareAmount' => $compareAmount,
            'compareLabel' => $this->formatDeltaLabel($compareAmount),
            'compositionItems' => $compositionItems,
            'compositionMax' => $compositionMax,
        ]);
    }

    public function print(Request $request, PayrollSlip $payrollSlip): View
    {
        $karyawan = $request->attributes->get('staff_karyawan');

        abort_unless((int) $payrollSlip->id_karyawan === (int) $karyawan->id_karyawan, 404);

        return view('payroll.print', [
            'payrollSlip' => $payrollSlip,
            'karyawan' => $karyawan,
            'viewer' => 'staff',
            'autoprint' => (bool) $request->boolean('autoprint'),
        ]);
    }

    private function buildHistorySeries(
        Collection $slips,
        \Illuminate\Support\Carbon $period,
        ?PayrollSlip $currentSlip,
        array $liveSummary,
        int $rangeMonths,
    ): Collection {
        $slipsByPeriod = $slips->keyBy(fn (PayrollSlip $slip) => $slip->period_month?->format('Y-m'));
        $selectedPeriodKey = $period->format('Y-m');

        return collect(range($rangeMonths - 1, 0))
            ->map(function (int $offset) use ($period, $slipsByPeriod, $selectedPeriodKey, $currentSlip, $liveSummary) {
                $month = $period->copy()->subMonths($offset)->startOfMonth();
                $periodKey = $month->format('Y-m');
                /** @var PayrollSlip|null $slip */
                $slip = $slipsByPeriod->get($periodKey);
                $isCurrent = $periodKey === $selectedPeriodKey;
                $amount = (int) ($slip?->net_amount ?? ($isCurrent ? ($currentSlip?->net_amount ?? $liveSummary['estimated_net_amount']) : 0));
                $statusLabel = $slip
                    ? $slip->statusLabel()
                    : ($isCurrent ? ($currentSlip ? $currentSlip->statusLabel() : 'Realtime') : 'Belum ada');

                return [
                    'period_key' => $periodKey,
                    'period_label' => $month->locale('id')->translatedFormat('F Y'),
                    'short_label' => Str::title($month->locale('id')->translatedFormat('M')),
                    'amount' => $amount,
                    'has_detail' => (bool) $slip,
                    'is_current' => $isCurrent,
                    'status_label' => $statusLabel,
                    'status_variant' => $slip
                        ? ($slip->status === PayrollSlip::STATUS_FINALIZED ? 'ok' : 'soft')
                        : ($isCurrent ? 'live' : 'muted'),
                    'hours_label' => $slip
                        ? $slip->paidHoursLabel()
                        : ($isCurrent ? $liveSummary['paid_hours_label'] : '0 jam'),
                    'scheme_label' => $slip
                        ? ($slip->salary_scheme === 'hourly' ? 'Per Jam' : 'Bulanan')
                        : ($isCurrent ? $liveSummary['salary_scheme_label'] : 'Belum ada'),
                    'slip' => $slip,
                ];
            });
    }

    private function buildMonthCards(
        Collection $slips,
        \Illuminate\Support\Carbon $period,
        ?PayrollSlip $currentSlip,
        array $liveSummary,
    ): Collection
    {
        $selectedPeriodKey = $period->format('Y-m');
        $cards = $slips
            ->map(function (PayrollSlip $slip) {
                $statusVariant = $slip->status === PayrollSlip::STATUS_FINALIZED ? 'ok' : 'soft';

                return [
                    'title' => $slip->periodLabel(),
                    'amount' => (int) $slip->net_amount,
                    'status_label' => $slip->statusLabel(),
                    'status_variant' => $statusVariant,
                    'hours_label' => $slip->paidHoursLabel(),
                    'scheme_label' => $slip->salary_scheme === 'hourly' ? 'Per Jam' : 'Bulanan',
                    'sub_label' => 'Slip ' . strtolower($slip->statusLabel()) . ' • ' . $slip->paidHoursLabel(),
                    'detail_route' => route('staff.payroll.period', ['period' => $slip->period_month?->format('Y-m')]),
                    'action_label' => 'Detail',
                    'tone' => $statusVariant === 'ok' ? 'final' : 'draft',
                    'period_key' => $slip->period_month?->format('Y-m'),
                ];
            })
            ->values();

        if ($cards->contains(fn (array $card) => ($card['period_key'] ?? null) === $selectedPeriodKey)) {
            return $cards;
        }

        return $cards
            ->prepend([
                'title' => $period->locale('id')->translatedFormat('F Y'),
                'amount' => (int) (($currentSlip?->net_amount) ?? $liveSummary['estimated_net_amount']),
                'status_label' => $currentSlip ? $currentSlip->statusLabel() : 'Realtime',
                'status_variant' => $currentSlip
                    ? ($currentSlip->status === PayrollSlip::STATUS_FINALIZED ? 'ok' : 'soft')
                    : 'live',
                'hours_label' => $currentSlip ? $currentSlip->paidHoursLabel() : $liveSummary['paid_hours_label'],
                'scheme_label' => $liveSummary['salary_scheme_label'],
                'sub_label' => 'Estimasi berjalan • ' . ($currentSlip ? $currentSlip->statusLabel() : $liveSummary['salary_scheme_label']),
                'detail_route' => route('staff.payroll.period', ['period' => $selectedPeriodKey]),
                'action_label' => 'Detail',
                'tone' => $currentSlip
                    ? ($currentSlip->status === PayrollSlip::STATUS_FINALIZED ? 'final' : 'draft')
                    : 'live',
                'period_key' => $selectedPeriodKey,
            ])
            ->values();
    }

    private function buildLiveCompositionItems(array $liveSummary): Collection
    {
        return collect([
            [
                'label' => 'Gaji dasar',
                'amount' => (int) ($liveSummary['base_amount'] ?? 0),
                'tone' => 'base',
            ],
            [
                'label' => 'Lembur',
                'amount' => (int) ($liveSummary['overtime_amount'] ?? 0),
                'tone' => 'positive',
            ],
            [
                'label' => 'Potongan alpa',
                'amount' => (int) ($liveSummary['auto_alpha_deduction'] ?? 0),
                'tone' => 'negative',
            ],
            [
                'label' => 'Potongan izin/sakit',
                'amount' => (int) ($liveSummary['auto_approved_leave_deduction'] ?? 0),
                'tone' => 'negative',
            ],
            [
                'label' => 'Potongan telat',
                'amount' => (int) ($liveSummary['auto_late_deduction'] ?? 0),
                'tone' => 'negative',
            ],
            [
                'label' => 'Potongan pulang cepat',
                'amount' => (int) ($liveSummary['auto_early_leave_deduction'] ?? 0),
                'tone' => 'negative',
            ],
        ])->filter(fn (array $item) => $item['amount'] > 0)->values();
    }

    private function buildChartMeta(Collection $historySeries): array
    {
        $width = 332;
        $height = 150;
        $paddingX = 18;
        $paddingTop = 18;
        $paddingBottom = 34;
        $baseline = $height - $paddingBottom;
        $values = $historySeries->values();
        $maxValue = max(1, (int) $values->max('amount'));
        $usableWidth = $width - ($paddingX * 2);
        $usableHeight = $baseline - $paddingTop;
        $step = $values->count() > 1
            ? $usableWidth / ($values->count() - 1)
            : 0;

        $points = $values->map(function (array $item, int $index) use ($paddingX, $step, $baseline, $usableHeight, $maxValue) {
            $ratio = $maxValue > 0 ? ((int) $item['amount'] / $maxValue) : 0;
            $x = $paddingX + ($index * $step);
            $y = $baseline - ($ratio * $usableHeight);

            return [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'label' => $item['short_label'],
                'amount' => (int) $item['amount'],
                'is_current' => (bool) $item['is_current'],
            ];
        });

        $polyline = $points
            ->map(fn (array $point) => $point['x'] . ',' . $point['y'])
            ->implode(' ');

        $firstPoint = $points->first();
        $lastPoint = $points->last();
        $areaPath = $points->isEmpty()
            ? ''
            : 'M ' . $firstPoint['x'] . ' ' . $baseline
                . ' L ' . $polyline
                . ' L ' . $lastPoint['x'] . ' ' . $baseline
                . ' Z';

        return [
            'width' => $width,
            'height' => $height,
            'baseline' => $baseline,
            'polyline' => $polyline,
            'area_path' => $areaPath,
            'points' => $points,
            'max_value' => $maxValue,
            'grid_lines' => collect([0.25, 0.5, 0.75])->map(
                fn (float $ratio) => round($baseline - ($usableHeight * $ratio), 2)
            ),
        ];
    }

    private function buildCompositionItems(PayrollSlip $payrollSlip): Collection
    {
        return collect([
            [
                'label' => 'Gaji dasar',
                'amount' => (int) ($payrollSlip->base_amount ?? 0),
                'tone' => 'base',
            ],
            [
                'label' => 'Lembur',
                'amount' => (int) ($payrollSlip->overtime_amount ?? 0),
                'tone' => 'positive',
            ],
            [
                'label' => 'Bonus',
                'amount' => (int) ($payrollSlip->bonus_amount ?? 0),
                'tone' => 'positive',
            ],
            [
                'label' => 'Potongan otomatis',
                'amount' => (int) ($payrollSlip->auto_deduction_amount ?? 0),
                'tone' => 'negative',
            ],
            [
                'label' => 'Potongan manual',
                'amount' => (int) ($payrollSlip->deduction_amount ?? 0),
                'tone' => 'negative',
            ],
        ])->filter(fn (array $item) => $item['amount'] > 0)->values();
    }

    private function formatDeltaLabel(?int $amount): ?string
    {
        if ($amount === null) {
            return null;
        }

        if ($amount === 0) {
            return 'Stabil dibanding periode sebelumnya';
        }

        $prefix = $amount > 0 ? 'Naik' : 'Turun';

        return $prefix . ' Rp ' . number_format(abs($amount), 0, ',', '.') . ' dari periode sebelumnya';
    }
}
