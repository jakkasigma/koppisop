<?php

namespace App\Http\Controllers;

use App\Models\StaffActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardStaffActivityController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'action' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $search = trim((string) ($data['q'] ?? ''));
        $action = trim((string) ($data['action'] ?? ''));
        $dateFrom = $data['date_from'] ?? null;
        $dateTo = $data['date_to'] ?? null;

        $query = StaffActivityLog::query()
            ->with('karyawan')
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('actor_name', 'like', '%' . $search . '%')
                        ->orWhere('action_label', 'like', '%' . $search . '%')
                        ->orWhere('summary', 'like', '%' . $search . '%')
                        ->orWhere('target_label', 'like', '%' . $search . '%');
                });
            })
            ->when($action !== '', fn ($builder) => $builder->where('action_key', $action))
            ->when($dateFrom, fn ($builder) => $builder->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($builder) => $builder->whereDate('created_at', '<=', $dateTo))
            ->latest('created_at');

        $summaryRows = (clone $query)->get(['id', 'karyawan_id', 'created_at']);
        $rows = $query->paginate(20)->withQueryString();

        $actionOptions = StaffActivityLog::query()
            ->select('action_key', 'action_label')
            ->distinct()
            ->orderBy('action_label')
            ->get();

        return view('dashboard.staff-activity.index', [
            'rows' => $rows,
            'actionOptions' => $actionOptions,
            'search' => $search,
            'selectedAction' => $action,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'summary' => [
                'total' => $summaryRows->count(),
                'today' => $summaryRows->filter(fn (StaffActivityLog $row) => $row->created_at?->isToday())->count(),
                'staff' => $summaryRows->pluck('karyawan_id')->filter()->unique()->count(),
            ],
        ]);
    }
}
