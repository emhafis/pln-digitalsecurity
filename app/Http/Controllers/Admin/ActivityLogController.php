<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $module = trim((string) $request->query('module', ''));
        $action = trim((string) $request->query('action', ''));
        $startDate = $this->queryDate($request, 'start_date');
        $endDate = $this->queryDate($request, 'end_date');

        $query = ActivityLog::query()
            ->with(['user:id,name,username', 'unit:id,unit_name'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('user_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($module !== '', fn ($q) => $q->where('module', $module))
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($startDate !== null, fn ($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate !== null, fn ($q) => $q->whereDate('created_at', '<=', $endDate))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $paginator = $query->paginate(25)->withQueryString();

        return Inertia::render('Admin/ActivityLog/Index', [
            'logs' => $paginator->getCollection()->map(fn (ActivityLog $log) => $this->transform($log))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => [
                'search' => $search,
                'module' => $module,
                'action' => $action,
                'start_date' => $startDate ?? '',
                'end_date' => $endDate ?? '',
            ],
            'modules' => ActivityLog::query()
                ->select('module')
                ->whereNotNull('module')
                ->distinct()
                ->orderBy('module')
                ->pluck('module')
                ->values(),
            'actions' => ActivityLog::query()
                ->select('action')
                ->whereNotNull('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action')
                ->values(),
        ]);
    }

    public function show(ActivityLog $activityLog)
    {
        $activityLog->loadMissing(['user:id,name,username', 'unit:id,unit_name']);

        return response()->json($this->transform($activityLog, true));
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(ActivityLog $log, bool $detail = false): array
    {
        $data = [
            'id' => $log->id,
            'user_name' => $log->user_name ?? $log->user?->name ?? $log->user?->username ?? '-',
            'action' => $log->action,
            'action_label' => $this->actionLabel($log->action),
            'module' => $log->module,
            'description' => $log->description,
            'unit_name' => $log->unit?->unit_name ?? '-',
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            'created_at_label' => $log->created_at?->format('d M Y H:i:s'),
        ];

        if ($detail) {
            $data['ip_address'] = $log->ip_address;
            $data['user_agent'] = $log->user_agent;
            $data['old_values'] = $log->old_values;
            $data['new_values'] = $log->new_values;
        }

        return $data;
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'created' => 'Dibuat',
            'updated' => 'Diperbarui',
            'deleted' => 'Dihapus',
            'generated' => 'Digenerate',
            'login' => 'Login',
            'logout' => 'Logout',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => ucfirst(str_replace('_', ' ', $action)),
        };
    }

    private function queryDate(Request $request, string $key): ?string
    {
        $raw = trim((string) $request->query($key, ''));
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
