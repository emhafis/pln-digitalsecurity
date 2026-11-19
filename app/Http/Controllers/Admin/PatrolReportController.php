<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PatrolReportExport;
use App\Http\Controllers\Concerns\ResolvesAttendanceScope;
use App\Http\Controllers\Controller;
use App\Models\PatrolSession;
use App\Models\SecurityPost;
use App\Models\SecurityTeam;
use App\Models\Shift;
use App\Models\Unit;
use App\Support\AttendanceClock;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PatrolReportController extends Controller
{
    use ResolvesAttendanceScope;

    public function index(Request $request): Response
    {
        $data = $this->buildReportData($request, false);

        return Inertia::render('Admin/PatrolReport/Index', $data);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $data = $this->buildReportData($request, true);

        $from = $data['filters']['date_from'] ?: 'awal';
        $to = $data['filters']['date_to'] ?: 'akhir';
        $filename = 'laporan-patroli-'.$from.'-sd-'.$to.'.xlsx';

        return Excel::download(new PatrolReportExport($data), $filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReportData(Request $request, bool $forExport): array
    {
        $scope = $this->attendanceScope($request);
        $isSuperadmin = $request->user()?->role === 'superadmin';

        $statuses = [
            PatrolSession::STATUS_PENDING,
            PatrolSession::STATUS_ACTIVE,
            PatrolSession::STATUS_COMPLETED,
            PatrolSession::STATUS_INCOMPLETE,
            PatrolSession::STATUS_NOT_STARTED,
        ];

        $status = (string) $request->query('status', '');
        if (! in_array($status, $statuses, true)) {
            $status = '';
        }

        $dateFrom = $this->queryDate($request, 'date_from');
        $dateTo = $this->queryDate($request, 'date_to');

        $requiresUnitSelection = false;
        $unitId = $this->resolveUnitFilter($request, $scope, $isSuperadmin, $requiresUnitSelection);
        $postId = $this->resolveScopedPostId($request, $scope, $unitId);
        $teamId = $this->resolveScopedTeamId($request, $scope, $unitId, $postId);
        $shiftId = $this->resolveScopedShiftId($request, $scope, $unitId);

        $filters = [
            'date_from' => $dateFrom ?? '',
            'date_to' => $dateTo ?? '',
            'unit_id' => $unitId ?? '',
            'security_post_id' => $postId ?? '',
            'security_team_id' => $teamId ?? '',
            'shift_id' => $shiftId ?? '',
            'status' => $status,
            'security_id' => '',
        ];

        $statusOptions = [
            ['value' => '', 'label' => 'Semua Status'],
            ['value' => PatrolSession::STATUS_COMPLETED, 'label' => 'Selesai'],
            ['value' => PatrolSession::STATUS_ACTIVE, 'label' => 'Sedang Berlangsung'],
            ['value' => PatrolSession::STATUS_INCOMPLETE, 'label' => 'Tidak Selesai'],
            ['value' => PatrolSession::STATUS_PENDING, 'label' => 'Belum Dimulai (Menunggu)'],
            ['value' => PatrolSession::STATUS_NOT_STARTED, 'label' => 'Belum Dimulai (Tidak Dimulai)'],
        ];

        $baseMeta = [
            'statuses' => $statusOptions,
            'units' => $this->attendanceUnits($scope),
            'posts' => $this->filterPosts($scope, $unitId),
            'teams' => $this->filterTeams($scope, $unitId, $postId),
            'shifts' => $this->filterShifts($scope, $unitId),
            'securities' => $this->filterSecurities($scope, $unitId, $postId, $teamId, $dateFrom, $dateTo),
            'filters' => $filters,
            'requires_unit_selection' => $requiresUnitSelection,
        ];

        $securityId = $this->resolveScopedSecurityId($request, $scope, $unitId, $postId, $teamId, $dateFrom, $dateTo);
        $baseMeta['filters']['security_id'] = $securityId ?? '';

        if ($requiresUnitSelection) {
            $empty = $this->emptyReport($baseMeta);
            $empty['filters'] = $baseMeta['filters'];
            $empty['securities'] = $baseMeta['securities'];
            $empty['statuses'] = $baseMeta['statuses'];
            $empty['units'] = $baseMeta['units'];
            $empty['posts'] = $baseMeta['posts'];
            $empty['teams'] = $baseMeta['teams'];
            $empty['shifts'] = $baseMeta['shifts'];
            $empty['requires_unit_selection'] = true;

            return $empty;
        }

        $sessionQuery = $this->baseSessionQuery($scope, $unitId, $postId, $teamId, $shiftId, $status, $dateFrom, $dateTo, $securityId);

        $summary = $this->buildSummary(clone $sessionQuery);
        $byUnit = $this->buildRecapByUnit(clone $sessionQuery);
        $byPost = $this->buildRecapByPost(clone $sessionQuery);
        $byTeam = $this->buildRecapByTeam(clone $sessionQuery);
        $securityActivity = $this->buildSecurityActivity($scope, $unitId, $postId, $teamId, $shiftId, $status, $dateFrom, $dateTo, $securityId);
        $incidents = $this->buildIncidents(clone $sessionQuery, $forExport);

        return array_merge($baseMeta, [
            'filters' => $baseMeta['filters'],
            'summary' => $summary,
            'by_unit' => $byUnit,
            'by_post' => $byPost,
            'by_team' => $byTeam,
            'security_activity' => $securityActivity,
            'incidents' => $incidents,
        ]);
    }

    /**
     * @param  array<int, int>|null  $scope
     */
    private function baseSessionQuery(?array $scope, ?int $unitId, ?int $postId, ?int $teamId, ?int $shiftId, string $status, ?string $dateFrom, ?string $dateTo, ?int $securityId): Builder
    {
        return DB::table('patrol_sessions')
            ->where(function ($q) use ($scope, $unitId, $postId, $teamId, $shiftId, $status, $dateFrom, $dateTo, $securityId) {
                if ($scope !== null) {
                    $q->whereIn('patrol_sessions.unit_id', $scope);
                }
                if ($unitId !== null) {
                    $q->where('patrol_sessions.unit_id', $unitId);
                }
                if ($postId !== null) {
                    $q->where('patrol_sessions.security_post_id', $postId);
                }
                if ($teamId !== null) {
                    $q->where('patrol_sessions.security_team_id', $teamId);
                }
                if ($shiftId !== null) {
                    $q->where('patrol_sessions.shift_id', $shiftId);
                }
                $this->applyStatusFilter($q, $status);
                if ($dateFrom !== null) {
                    $q->whereDate('patrol_sessions.work_date', '>=', $dateFrom);
                }
                if ($dateTo !== null) {
                    $q->whereDate('patrol_sessions.work_date', '<=', $dateTo);
                }
                if ($securityId !== null) {
                    $q->whereExists(function ($sub) use ($securityId) {
                        $sub->select(DB::raw(1))
                            ->from('patrol_session_members as psmf')
                            ->whereColumn('psmf.patrol_session_id', 'patrol_sessions.id')
                            ->where('psmf.security_id', $securityId);
                    });
                }
            });
    }

    private function applyStatusFilter($query, string $status): void
    {
        if ($status === '') {
            return;
        }

        $query->where('patrol_sessions.status', $status);
    }

    /**
     * Agregasi per sesi: titik wajib, titik terpenuhi unik, total aktivitas scan, total insiden.
     */
    private function sessionAggBase(Builder $sessionQuery): Builder
    {
        $points = DB::table('patrol_session_points')
            ->select('patrol_session_id', DB::raw('COUNT(*) as required_points'))
            ->groupBy('patrol_session_id');

        $scans = DB::table('patrol_scans')
            ->select(
                'patrol_session_id',
                DB::raw('COUNT(DISTINCT patrol_point_id) as fulfilled_points'),
                DB::raw('COUNT(*) as scan_activity_count'),
                DB::raw('SUM(CASE WHEN has_incident = 1 THEN 1 ELSE 0 END) as incident_count')
            )
            ->groupBy('patrol_session_id');

        return DB::query()->fromSub($sessionQuery->select('patrol_sessions.*'), 's')
            ->leftJoinSub($points, 'p', 'p.patrol_session_id', '=', 's.id')
            ->leftJoinSub($scans, 'sc', 'sc.patrol_session_id', '=', 's.id')
            ->selectRaw('s.*, COALESCE(p.required_points,0) as required_points, COALESCE(sc.fulfilled_points,0) as fulfilled_points, COALESCE(sc.scan_activity_count,0) as scan_activity_count, COALESCE(sc.incident_count,0) as incident_count');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(Builder $sessionQuery): array
    {
        $agg = $this->sessionAggBase($sessionQuery);

        $row = DB::query()->fromSub($agg, 'a')->selectRaw(
            'COUNT(*) as total_sessions,'
            .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_sessions,'
            .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_sessions,'
            .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as incomplete_sessions,'
            .'SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as not_started_sessions,'
            .'COALESCE(SUM(required_points),0) as total_required_points,'
            .'COALESCE(SUM(fulfilled_points),0) as total_fulfilled_points,'
            .'COALESCE(SUM(scan_activity_count),0) as total_scan_activity,'
            .'COALESCE(SUM(incident_count),0) as total_incidents',
            [
                PatrolSession::STATUS_COMPLETED,
                PatrolSession::STATUS_ACTIVE,
                PatrolSession::STATUS_INCOMPLETE,
                PatrolSession::STATUS_PENDING,
                PatrolSession::STATUS_NOT_STARTED,
            ]
        )->first();

        $required = (int) ($row->total_required_points ?? 0);
        $fulfilled = (int) ($row->total_fulfilled_points ?? 0);

        return [
            'total_sessions' => (int) ($row->total_sessions ?? 0),
            'completed_sessions' => (int) ($row->completed_sessions ?? 0),
            'active_sessions' => (int) ($row->active_sessions ?? 0),
            'incomplete_sessions' => (int) ($row->incomplete_sessions ?? 0),
            'not_started_sessions' => (int) ($row->not_started_sessions ?? 0),
            'total_required_points' => $required,
            'total_fulfilled_points' => $fulfilled,
            'total_scanned_points' => $fulfilled,
            'total_scan_activity' => (int) ($row->total_scan_activity ?? 0),
            'completion_percentage' => $this->pct($fulfilled, $required),
            'total_incidents' => (int) ($row->total_incidents ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRecapByUnit(Builder $sessionQuery): array
    {
        $agg = $this->sessionAggBase($sessionQuery);

        return DB::query()->fromSub($agg, 'a')
            ->selectRaw(
                'unit_id, unit_name, unit_code, COUNT(*) as total_sessions,'
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_sessions,'
                .'SUM(CASE WHEN status <> ? THEN 1 ELSE 0 END) as not_completed_sessions,'
                .'COALESCE(SUM(required_points),0) as required_points,'
                .'COALESCE(SUM(fulfilled_points),0) as fulfilled_points,'
                .'COALESCE(SUM(scan_activity_count),0) as scan_activity_count,'
                .'COALESCE(SUM(incident_count),0) as incidents',
                [PatrolSession::STATUS_COMPLETED, PatrolSession::STATUS_COMPLETED]
            )
            ->groupBy('unit_id', 'unit_name', 'unit_code')
            ->orderBy('unit_name')
            ->get()
            ->map(fn ($r) => $this->mapRecapRow($r, ['unit_name' => $r->unit_name, 'unit_code' => $r->unit_code]))
            ->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRecapByPost(Builder $sessionQuery): array
    {
        $agg = $this->sessionAggBase($sessionQuery);

        return DB::query()->fromSub($agg, 'a')
            ->selectRaw(
                'security_post_id, post_name, post_code, unit_name, unit_code, COUNT(*) as total_sessions,'
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_sessions,'
                .'COALESCE(SUM(required_points),0) as required_points,'
                .'COALESCE(SUM(fulfilled_points),0) as fulfilled_points,'
                .'COALESCE(SUM(scan_activity_count),0) as scan_activity_count,'
                .'COALESCE(SUM(incident_count),0) as incidents',
                [PatrolSession::STATUS_COMPLETED]
            )
            ->groupBy('security_post_id', 'post_name', 'post_code', 'unit_name', 'unit_code')
            ->orderBy('unit_name')->orderBy('post_name')
            ->get()
            ->map(fn ($r) => $this->mapRecapRow($r, [
                'post_name' => $r->post_name,
                'post_code' => $r->post_code,
                'unit_name' => $r->unit_name,
            ]))
            ->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRecapByTeam(Builder $sessionQuery): array
    {
        $agg = $this->sessionAggBase($sessionQuery);

        return DB::query()->fromSub($agg, 'a')
            ->selectRaw(
                'security_team_id, team_name, team_code, post_name, post_code, COUNT(*) as total_sessions,'
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_sessions,'
                .'COALESCE(SUM(required_points),0) as required_points,'
                .'COALESCE(SUM(fulfilled_points),0) as fulfilled_points,'
                .'COALESCE(SUM(scan_activity_count),0) as scan_activity_count,'
                .'COALESCE(SUM(incident_count),0) as incidents',
                [PatrolSession::STATUS_COMPLETED]
            )
            ->groupBy('security_team_id', 'team_name', 'team_code', 'post_name', 'post_code')
            ->orderBy('post_name')->orderBy('team_name')
            ->get()
            ->map(fn ($r) => $this->mapRecapRow($r, [
                'team_name' => $r->team_name,
                'team_code' => $r->team_code,
                'post_name' => $r->post_name,
            ]))
            ->values()->all();
    }

    /**
     * @param  array<int, int>|null  $scope
     * @return array<int, array<string, mixed>>
     */
    private function buildSecurityActivity(?array $scope, ?int $unitId, ?int $postId, ?int $teamId, ?int $shiftId, string $status, ?string $dateFrom, ?string $dateTo, ?int $securityId): array
    {
        $sessionQuery = $this->baseSessionQuery($scope, $unitId, $postId, $teamId, $shiftId, $status, $dateFrom, $dateTo, $securityId);
        $sessionIds = $sessionQuery->select('patrol_sessions.id');

        $assigned = DB::table('patrol_session_members as m')
            ->joinSub($sessionIds, 'fs', 'fs.id', '=', 'm.patrol_session_id')
            ->select('m.security_id', 'm.security_name', 'm.security_registration_number', DB::raw('COUNT(DISTINCT m.patrol_session_id) as assigned_sessions'))
            ->groupBy('m.security_id', 'm.security_name', 'm.security_registration_number');

        $scans = DB::table('patrol_scans as sc')
            ->joinSub($sessionIds, 'fs2', 'fs2.id', '=', 'sc.patrol_session_id')
            ->select(
                'sc.security_id',
                DB::raw('COUNT(DISTINCT sc.patrol_session_id) as participated_sessions'),
                DB::raw('COUNT(*) as scan_activity_count'),
                DB::raw('SUM(CASE WHEN sc.has_incident = 1 THEN 1 ELSE 0 END) as incident_reports')
            )
            ->groupBy('sc.security_id');

        return DB::query()->fromSub($assigned, 'a')
            ->leftJoinSub($scans, 'sc', 'sc.security_id', '=', 'a.security_id')
            ->selectRaw('a.security_id, a.security_name, a.security_registration_number, a.assigned_sessions, COALESCE(sc.participated_sessions,0) as participated_sessions, COALESCE(sc.scan_activity_count,0) as scan_activity_count, COALESCE(sc.incident_reports,0) as incident_reports')
            ->orderBy('a.security_name')
            ->get()
            ->map(fn ($r) => [
                'security_id' => (int) $r->security_id,
                'security_name' => $r->security_name,
                'security_registration_number' => $r->security_registration_number,
                'assigned_sessions' => (int) $r->assigned_sessions,
                'participated_sessions' => (int) $r->participated_sessions,
                'scan_activity_count' => (int) $r->scan_activity_count,
                'scanned_points' => (int) $r->scan_activity_count, // kompatibilitas nama lama = total aktivitas scan
                'incident_reports' => (int) $r->incident_reports,
            ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildIncidents(Builder $sessionQuery, bool $forExport): array
    {
        $sessionIds = $sessionQuery->select('patrol_sessions.id');

        $query = DB::table('patrol_scans as sc')
            ->join('patrol_sessions as s', 's.id', '=', 'sc.patrol_session_id')
            ->joinSub($sessionIds, 'fs', 'fs.id', '=', 'sc.patrol_session_id')
            ->where('sc.has_incident', 1)
            ->select('sc.id as scan_id', 'sc.patrol_session_id', 'sc.scanned_at', 'sc.patrol_point_name', 'sc.security_name', 'sc.incident_description', 'sc.incident_photo_path', 's.unit_name', 's.post_name', 's.team_name', 's.work_date')
            ->orderByDesc('sc.scanned_at')->orderByDesc('sc.id');

        $rows = $forExport ? $query->get() : $query->limit(200)->get();

        return $rows->map(function ($r) {
            $scannedAt = $r->scanned_at ? Carbon::parse($r->scanned_at) : null;
            $workDate = $r->work_date ? Carbon::parse($r->work_date) : null;

            return [
                'scan_id' => (int) $r->scan_id,
                'patrol_session_id' => (int) $r->patrol_session_id,
                'scanned_at' => AttendanceClock::formatWib($scannedAt),
                'work_date' => $workDate ? $workDate->format('d M Y') : '-',
                'unit_name' => $r->unit_name,
                'post_name' => $r->post_name,
                'team_name' => $r->team_name,
                'patrol_point_name' => $r->patrol_point_name,
                'security_name' => $r->security_name,
                'incident_description' => $r->incident_description,
                'has_photo' => ! empty($r->incident_photo_path),
                'photo_label' => ! empty($r->incident_photo_path) ? 'Ada' : 'Tidak ada',
                'detail_url' => route('admin.patrol-session.show', $r->patrol_session_id),
            ];
        })->values()->all();
    }

    private function mapRecapRow(object $r, array $extra): array
    {
        $required = (int) $r->required_points;
        $fulfilled = (int) ($r->fulfilled_points ?? $r->scanned_points ?? 0);
        $base = [
            'total_sessions' => (int) $r->total_sessions,
            'completed_sessions' => (int) $r->completed_sessions,
            'required_points' => $required,
            'fulfilled_points' => $fulfilled,
            'scanned_points' => $fulfilled, // kompatibilitas: titik terpenuhi unik
            'scan_activity_count' => (int) ($r->scan_activity_count ?? 0),
            'completion_percentage' => $this->pct($fulfilled, $required),
            'incidents' => (int) $r->incidents,
        ];
        if (property_exists($r, 'not_completed_sessions')) {
            $base['not_completed_sessions'] = (int) $r->not_completed_sessions;
        }

        return array_merge($extra, $base);
    }

    private function pct(int $fulfilled, int $required): float
    {
        if ($required <= 0) {
            return 0.00;
        }

        return round(($fulfilled / $required) * 100, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyReport(array $baseMeta): array
    {
        return array_merge($baseMeta, [
            'summary' => [
                'total_sessions' => 0,
                'completed_sessions' => 0,
                'active_sessions' => 0,
                'incomplete_sessions' => 0,
                'not_started_sessions' => 0,
                'total_required_points' => 0,
                'total_fulfilled_points' => 0,
                'total_scanned_points' => 0,
                'total_scan_activity' => 0,
                'completion_percentage' => 0.00,
                'total_incidents' => 0,
            ],
            'by_unit' => [],
            'by_post' => [],
            'by_team' => [],
            'security_activity' => [],
            'incidents' => [],
        ]);
    }

    /**
     * @param  array<int, int>|null  $scope
     */
    private function resolveUnitFilter(Request $request, ?array $scope, bool $isSuperadmin, bool &$requires): ?int
    {
        $requires = false;
        $raw = trim((string) $request->query('unit_id', ''));

        if ($isSuperadmin) {
            if ($raw === '' || ! is_numeric($raw)) {
                $requires = true;

                return null;
            }

            $candidate = (int) $raw;
            $exists = Unit::query()->whereKey($candidate)->exists();
            if (! $exists) {
                $requires = true;

                return null;
            }

            return $candidate;
        }

        if ($raw === '' || ! is_numeric($raw)) {
            if ($scope === null) {
                return null;
            }
            $userUnitId = $request->user()?->unit_id;

            return $userUnitId !== null && in_array((int) $userUnitId, $scope, true)
                ? (int) $userUnitId
                : null;
        }

        $candidate = (int) $raw;
        if ($scope !== null && ! in_array($candidate, $scope, true)) {
            return null;
        }

        return $candidate;
    }

    /**
     * @param  array<int, int>|null  $scope
     */
    private function resolveScopedPostId(Request $request, ?array $scope, ?int $unitId): ?int
    {
        $raw = $request->query('security_post_id');
        if (! is_numeric($raw)) {
            return null;
        }
        $id = (int) $raw;
        $post = SecurityPost::query()->find($id);
        if ($post === null) {
            return null;
        }
        if ($scope !== null && ! in_array((int) $post->unit_id, $scope, true)) {
            return null;
        }
        if ($unitId !== null && (int) $post->unit_id !== $unitId) {
            return null;
        }

        return $id;
    }

    /**
     * @param  array<int, int>|null  $scope
     */
    private function resolveScopedTeamId(Request $request, ?array $scope, ?int $unitId, ?int $postId): ?int
    {
        $raw = $request->query('security_team_id');
        if (! is_numeric($raw)) {
            return null;
        }
        $id = (int) $raw;
        $team = SecurityTeam::query()->with('securityPost:id,unit_id')->find($id);
        if ($team === null || $team->securityPost === null) {
            return null;
        }
        $teamUnitId = (int) $team->securityPost->unit_id;
        if ($scope !== null && ! in_array($teamUnitId, $scope, true)) {
            return null;
        }
        if ($unitId !== null && $teamUnitId !== $unitId) {
            return null;
        }
        if ($postId !== null && (int) $team->security_post_id !== $postId) {
            return null;
        }

        return $id;
    }

    /**
     * @param  array<int, int>|null  $scope
     */
    private function resolveScopedShiftId(Request $request, ?array $scope, ?int $unitId): ?int
    {
        $raw = $request->query('shift_id');
        if (! is_numeric($raw)) {
            return null;
        }
        $id = (int) $raw;
        $shift = Shift::query()->find($id, ['id', 'unit_id']);
        if ($shift === null) {
            return null;
        }
        if ($scope !== null && ! in_array((int) $shift->unit_id, $scope, true)) {
            return null;
        }
        if ($unitId !== null && (int) $shift->unit_id !== $unitId) {
            return null;
        }

        return $id;
    }

    /**
     * @param  array<int, int>|null  $scope
     */
    private function resolveScopedSecurityId(Request $request, ?array $scope, ?int $unitId, ?int $postId, ?int $teamId, ?string $dateFrom, ?string $dateTo): ?int
    {
        $raw = $request->query('security_id');
        if (! is_numeric($raw)) {
            return null;
        }
        $id = (int) $raw;
        $allowed = $this->filterSecurities($scope, $unitId, $postId, $teamId, $dateFrom, $dateTo);

        return collect($allowed)->firstWhere('value', $id) ? $id : null;
    }

    /**
     * @param  array<int, int>|null  $scope
     * @return array<int, array{value:int,label:string}>
     */
    private function filterPosts(?array $scope, ?int $unitId): array
    {
        return SecurityPost::query()
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope))
            ->when($unitId !== null, fn ($q) => $q->where('unit_id', $unitId))
            ->orderBy('name')
            ->get(['id', 'name', 'unit_id'])
            ->map(fn (SecurityPost $post) => [
                'value' => $post->id,
                'label' => $post->name,
                'unit_id' => $post->unit_id,
            ])->values()->toArray();
    }

    /**
     * @param  array<int, int>|null  $scope
     * @return array<int, array{value:int,label:string}>
     */
    private function filterTeams(?array $scope, ?int $unitId, ?int $postId): array
    {
        return SecurityTeam::query()
            ->with('securityPost:id,unit_id')
            ->when($scope !== null, fn ($q) => $q->whereHas('securityPost', fn ($p) => $p->whereIn('unit_id', $scope)))
            ->when($unitId !== null, fn ($q) => $q->whereHas('securityPost', fn ($p) => $p->where('unit_id', $unitId)))
            ->when($postId !== null, fn ($q) => $q->where('security_post_id', $postId))
            ->orderBy('name')
            ->get(['id', 'name', 'security_post_id'])
            ->map(fn (SecurityTeam $team) => [
                'value' => $team->id,
                'label' => $team->name,
                'security_post_id' => $team->security_post_id,
                'unit_id' => $team->securityPost?->unit_id,
            ])->values()->toArray();
    }

    /**
     * @param  array<int, int>|null  $scope
     * @return array<int, array{value:int,label:string}>
     */
    private function filterShifts(?array $scope, ?int $unitId): array
    {
        return Shift::query()
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope))
            ->when($unitId !== null, fn ($q) => $q->where('unit_id', $unitId))
            ->orderBy('name')
            ->get(['id', 'name', 'unit_id'])
            ->map(fn (Shift $shift) => [
                'value' => $shift->id,
                'label' => $shift->name,
                'unit_id' => $shift->unit_id,
            ])->values()->toArray();
    }

    /**
     * @param  array<int, int>|null  $scope
     * @return array<int, array{value:int,label:string}>
     */
    private function filterSecurities(?array $scope, ?int $unitId, ?int $postId, ?int $teamId, ?string $dateFrom, ?string $dateTo): array
    {
        $q = DB::table('patrol_session_members as m')
            ->join('patrol_sessions as s', 's.id', '=', 'm.patrol_session_id')
            ->when($scope !== null, fn ($qq) => $qq->whereIn('s.unit_id', $scope))
            ->when($unitId !== null, fn ($qq) => $qq->where('s.unit_id', $unitId))
            ->when($postId !== null, fn ($qq) => $qq->where('s.security_post_id', $postId))
            ->when($teamId !== null, fn ($qq) => $qq->where('s.security_team_id', $teamId))
            ->when($dateFrom !== null, fn ($qq) => $qq->whereDate('s.work_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($qq) => $qq->whereDate('s.work_date', '<=', $dateTo));

        return $q->select('m.security_id', 'm.security_name', 'm.security_registration_number')
            ->distinct()
            ->orderBy('m.security_name')
            ->limit(300)
            ->get()
            ->map(fn ($r) => [
                'value' => (int) $r->security_id,
                'label' => trim(($r->security_name ?? '').' '.($r->security_registration_number ? '('.$r->security_registration_number.')' : '')),
            ])->values()->toArray();
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
