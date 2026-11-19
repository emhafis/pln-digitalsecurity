<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesAttendanceScope;
use App\Http\Controllers\Controller;
use App\Models\PatrolScan;
use App\Models\PatrolScanPhoto;
use App\Models\PatrolSession;
use App\Models\SecurityPost;
use App\Models\SecurityTeam;
use App\Models\Shift;
use App\Models\Unit;
use App\Support\AttendanceClock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatrolSessionController extends Controller
{
    use ResolvesAttendanceScope;

    public function index(Request $request): Response
    {
        $scope = $this->attendanceScope($request);
        $isSuperadmin = $request->user()?->role === 'superadmin';

        $sortColumns = [
            'work_date' => 'patrol_sessions.work_date',
            'status' => 'patrol_sessions.status',
            'unit' => 'patrol_sessions.unit_name',
            'started_at' => 'patrol_sessions.started_at',
        ];

        $sort = (string) $request->query('sort', 'work_date');
        if (! array_key_exists($sort, $sortColumns)) {
            $sort = 'work_date';
        }
        $dir = $request->query('dir', 'desc') === 'asc' ? 'asc' : 'desc';

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

        if (! $request->query->has('date_from') && ! $request->query->has('date_to')) {
            $today = AttendanceClock::todayWib();
            $dateFrom = $today;
            $dateTo = $today;
        }

        $requiresUnitSelection = false;
        $unitId = $this->resolveUnitFilter($request, $scope, $isSuperadmin, $requiresUnitSelection);
        $postId = $this->resolveScopedPostId($request, $scope, $unitId);
        $teamId = $this->resolveScopedTeamId($request, $scope, $unitId, $postId);
        $shiftId = $this->resolveScopedShiftId($request, $scope, $unitId);

        $statusOptions = [
            ['value' => '', 'label' => 'Semua Status'],
            ['value' => PatrolSession::STATUS_PENDING, 'label' => 'Menunggu'],
            ['value' => PatrolSession::STATUS_ACTIVE, 'label' => 'Aktif'],
            ['value' => PatrolSession::STATUS_COMPLETED, 'label' => 'Selesai'],
            ['value' => PatrolSession::STATUS_INCOMPLETE, 'label' => 'Tidak Lengkap'],
            ['value' => PatrolSession::STATUS_NOT_STARTED, 'label' => 'Tidak Dimulai'],
        ];

        $baseProps = [
            'statuses' => $statusOptions,
            'units' => $this->attendanceUnits($scope),
            'posts' => $this->filterPosts($scope, $unitId),
            'teams' => $this->filterTeams($scope, $unitId, $postId),
            'shifts' => $this->filterShifts($scope, $unitId),
            'filters' => [
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
                'unit_id' => $unitId ?? '',
                'security_post_id' => $postId ?? '',
                'security_team_id' => $teamId ?? '',
                'shift_id' => $shiftId ?? '',
                'status' => $status,
                'sort' => $sort,
                'dir' => $dir,
            ],
            'requires_unit_selection' => $requiresUnitSelection,
        ];

        if ($requiresUnitSelection) {
            return Inertia::render('Admin/PatrolMonitoring/Index', array_merge($baseProps, [
                'sessions' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                ],
            ]));
        }

        $query = PatrolSession::query()
            ->when($scope !== null, fn ($q) => $q->whereIn('patrol_sessions.unit_id', $scope))
            ->when($unitId !== null, fn ($q) => $q->where('patrol_sessions.unit_id', $unitId))
            ->when($postId !== null, fn ($q) => $q->where('patrol_sessions.security_post_id', $postId))
            ->when($teamId !== null, fn ($q) => $q->where('patrol_sessions.security_team_id', $teamId))
            ->when($shiftId !== null, fn ($q) => $q->where('patrol_sessions.shift_id', $shiftId))
            ->when($status !== '', fn ($q) => $q->where('patrol_sessions.status', $status))
            ->when($dateFrom !== null, fn ($q) => $q->whereDate('patrol_sessions.work_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($q) => $q->whereDate('patrol_sessions.work_date', '<=', $dateTo))
            ->withCount(['points as required_points'])
            ->withCount(['scans as total_scans'])
            ->withCount(['scans as fulfilled_points' => function ($q) {
                $q->select(\DB::raw('count(distinct patrol_point_id)'));
            }])
            ->orderBy($sortColumns[$sort], $dir)
            ->orderByDesc('patrol_sessions.id');

        $paginator = $query->paginate(50)->withQueryString();

        $sessions = $paginator->getCollection()->map(function (PatrolSession $session) {
            $required = (int) $session->required_points;
            $fulfilled = (int) $session->fulfilled_points;
            $progress = $required > 0 ? (int) round(($fulfilled / $required) * 100) : 0;

            return [
                'id' => $session->id,
                'work_date' => $session->work_date?->toDateString(),
                'work_date_label' => $session->work_date?->format('d M Y'),
                'unit_name' => $session->unit_name,
                'unit_code' => $session->unit_code,
                'post_name' => $session->post_name,
                'post_code' => $session->post_code,
                'team_name' => $session->team_name,
                'team_code' => $session->team_code,
                'shift_name' => $session->shift_name,
                'shift_code' => $session->shift_code,
                'shift_time' => $this->formatShiftTime($session->shift_start_time, $session->shift_end_time),
                'status' => $session->status,
                'status_label' => $this->statusLabel($session->status),
                'started_at' => AttendanceClock::formatWib($session->started_at),
                'ended_at' => AttendanceClock::formatWib($session->ended_at),
                'required_points' => $required,
                'fulfilled_points' => $fulfilled,
                'scanned_points' => $fulfilled,
                'total_scans' => (int) $session->total_scans,
                'progress' => $progress,
            ];
        })->values();

        return Inertia::render('Admin/PatrolMonitoring/Index', array_merge($baseProps, [
            'sessions' => $sessions,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]));
    }

    public function show(Request $request, PatrolSession $patrolSession): Response
    {
        $this->authorizeSessionAccess($request, $patrolSession);

        $patrolSession->loadCount(['points as required_points']);
        $patrolSession->load([
            'members' => fn ($q) => $q->orderBy('id'),
            'points' => fn ($q) => $q->orderBy('sequence'),
            'scans' => fn ($q) => $q->with('photos')->orderBy('scanned_at')->orderBy('id'),
        ]);

        $required = (int) $patrolSession->required_points;
        $allScans = $patrolSession->scans;
        $totalScans = $allScans->count();
        $scansByPoint = $allScans->groupBy('patrol_point_id');
        $fulfilledPoints = $scansByPoint->count();
        $progress = $required > 0 ? (int) round(($fulfilledPoints / $required) * 100) : 0;

        $scanCountsBySecurity = $allScans->groupBy('security_id')->map->count();

        $members = $patrolSession->members->map(function ($member) use ($scanCountsBySecurity) {
            $count = (int) ($scanCountsBySecurity->get($member->security_id, 0));

            return [
                'id' => $member->id,
                'security_id' => $member->security_id,
                'security_name' => $member->security_name,
                'security_registration_number' => $member->security_registration_number,
                'scan_count' => $count,
                'scan_activity' => $count > 0 ? 'Sudah melakukan scan ('.$count.' aktivitas)' : 'Belum melakukan scan',
            ];
        })->values();

        $memberCount = $members->count();

        $points = $patrolSession->points->map(function ($point) use ($scansByPoint, $patrolSession, $members, $memberCount) {
            $pointScans = $scansByPoint->get($point->patrol_point_id, collect())->sortBy('scanned_at')->values();
            $scanCount = $pointScans->count();
            $fulfilled = $scanCount > 0;
            $scansByMember = $pointScans->groupBy('security_id');
            $membersScannedCount = $scansByMember->count();
            $membersNotScannedCount = max($memberCount - $membersScannedCount, 0);

            $memberCells = $members->map(function ($member) use ($scansByMember, $patrolSession) {
                $memberScans = $scansByMember->get($member['security_id'], collect())->sortBy('scanned_at')->values();
                $count = $memberScans->count();

                return [
                    'security_id' => $member['security_id'],
                    'security_name' => $member['security_name'],
                    'security_registration_number' => $member['security_registration_number'],
                    'scan_count' => $count,
                    'status' => $count > 0 ? 'scanned' : 'not_scanned',
                    'scans' => $memberScans->map(function (PatrolScan $scan) use ($patrolSession) {
                        $photos = $this->scanPhotoPayloads($patrolSession, $scan);

                        return [
                            'id' => $scan->id,
                            'scanned_at' => AttendanceClock::formatWib($scan->scanned_at),
                            'security_name' => $scan->security_name,
                            'security_registration_number' => $scan->security_registration_number,
                            'condition' => $scan->has_incident ? 'incident' : 'safe',
                            'condition_label' => $scan->has_incident ? 'Ada Insiden' : 'Aman',
                            'has_incident' => (bool) $scan->has_incident,
                            'incident_description' => $scan->incident_description,
                            'latitude' => $scan->latitude,
                            'longitude' => $scan->longitude,
                            'accuracy' => $scan->accuracy,
                            'photos' => $photos,
                        ];
                    })->values()->all(),
                ];
            })->values()->all();

            $lastScan = $pointScans->last();

            return [
                'id' => $point->id,
                'patrol_point_id' => $point->patrol_point_id,
                'name' => $point->patrol_point_name,
                'sequence' => $point->sequence,
                'fulfilled' => $fulfilled,
                'scanned' => $fulfilled,
                'point_status' => $fulfilled ? (($lastScan && $lastScan->has_incident) ? 'incident' : 'safe') : 'not_scanned',
                'point_status_label' => $fulfilled ? (($lastScan && $lastScan->has_incident) ? 'Ada Insiden' : 'Sudah Dipindai') : 'Belum Dipindai',
                'total_scans' => $scanCount,
                'members_scanned_count' => $membersScannedCount,
                'members_not_scanned_count' => $membersNotScannedCount,
                'member_cells' => $memberCells,
                'last_scanned_at' => $lastScan ? AttendanceClock::formatWib($lastScan->scanned_at) : null,
            ];
        })->values();

        $incidents = $allScans
            ->filter(fn (PatrolScan $scan) => (bool) $scan->has_incident)
            ->sortBy('scanned_at')
            ->values()
            ->map(function (PatrolScan $scan) use ($patrolSession) {
                $photos = $this->scanPhotoPayloads($patrolSession, $scan);

                return [
                    'id' => $scan->id,
                    'patrol_point_name' => $scan->patrol_point_name,
                    'description' => $scan->incident_description,
                    'reported_at' => AttendanceClock::formatWib($scan->scanned_at),
                    'reported_by_name' => $scan->security_name,
                    'reported_by_registration_number' => $scan->security_registration_number,
                    'has_photo' => count($photos) > 0,
                    'photo_url' => $photos[0]['url'] ?? ($scan->incident_photo_path
                        ? route('admin.patrol-session.scan.photo', ['patrolSession' => $patrolSession->id, 'scan' => $scan->id])
                        : null),
                    'photos' => $photos,
                ];
            })->values();

        return Inertia::render('Admin/PatrolMonitoring/Show', [
            'session' => [
                'id' => $patrolSession->id,
                'unit_name' => $patrolSession->unit_name,
                'unit_code' => $patrolSession->unit_code,
                'post_name' => $patrolSession->post_name,
                'post_code' => $patrolSession->post_code,
                'team_name' => $patrolSession->team_name,
                'team_code' => $patrolSession->team_code,
                'shift_name' => $patrolSession->shift_name,
                'shift_code' => $patrolSession->shift_code,
                'work_date' => $patrolSession->work_date?->toDateString(),
                'work_date_label' => $patrolSession->work_date?->format('d M Y'),
                'shift_time' => $this->formatShiftTime($patrolSession->shift_start_time, $patrolSession->shift_end_time),
                'status' => $patrolSession->status,
                'status_label' => $this->statusLabel($patrolSession->status),
                'started_at' => AttendanceClock::formatWib($patrolSession->started_at),
                'ended_at' => AttendanceClock::formatWib($patrolSession->ended_at),
                'required_points' => $required,
                'fulfilled_points' => $fulfilledPoints,
                'scanned_points' => $fulfilledPoints,
                'total_scans' => $totalScans,
                'progress' => $progress,
            ],
            'members' => $members,
            'points' => $points,
            'incidents' => $incidents,
        ]);
    }

    public function scanPhoto(Request $request, PatrolSession $patrolSession, PatrolScan $scan): StreamedResponse
    {
        $this->authorizeSessionAccess($request, $patrolSession);

        if ((int) $scan->patrol_session_id !== (int) $patrolSession->id || empty($scan->incident_photo_path)) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($scan->incident_photo_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($scan->incident_photo_path);
    }

    public function scanPhotoItem(Request $request, PatrolSession $patrolSession, PatrolScan $scan, PatrolScanPhoto $photo): StreamedResponse
    {
        $this->authorizeSessionAccess($request, $patrolSession);

        if ((int) $scan->patrol_session_id !== (int) $patrolSession->id || (int) $photo->patrol_scan_id !== (int) $scan->id) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($photo->photo_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($photo->photo_path);
    }

    /**
     * @return array<int, array{id:int,url:string,sequence:int,legacy:bool}>
     */
    private function scanPhotoPayloads(PatrolSession $patrolSession, ?PatrolScan $scan): array
    {
        if ($scan === null) {
            return [];
        }

        $photos = [];
        $hasNew = $scan->relationLoaded('photos') ? $scan->photos->count() > 0 : $scan->photos()->exists();
        $collection = $scan->relationLoaded('photos') ? $scan->photos : $scan->photos()->orderBy('sequence')->get();

        foreach ($collection as $photo) {
            $photos[] = [
                'id' => (int) $photo->id,
                'url' => route('admin.patrol-session.scan.photo-item', [
                    'patrolSession' => $patrolSession->id,
                    'scan' => $scan->id,
                    'photo' => $photo->id,
                ]),
                'sequence' => (int) $photo->sequence,
                'legacy' => false,
            ];
        }

        if (! $hasNew && $scan->incident_photo_path) {
            $photos[] = [
                'id' => 0,
                'url' => route('admin.patrol-session.scan.photo', ['patrolSession' => $patrolSession->id, 'scan' => $scan->id]),
                'sequence' => 1,
                'legacy' => true,
            ];
        }

        return $photos;
    }

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

    private function formatShiftTime($start, $end): string
    {
        $s = $start ? substr((string) $start, 0, 5) : '';
        $e = $end ? substr((string) $end, 0, 5) : '';

        return $s && $e ? $s.' - '.$e : ($s ?: $e ?: '-');
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            PatrolSession::STATUS_PENDING => 'Menunggu',
            PatrolSession::STATUS_ACTIVE => 'Aktif',
            PatrolSession::STATUS_COMPLETED => 'Selesai',
            PatrolSession::STATUS_INCOMPLETE => 'Tidak Lengkap',
            PatrolSession::STATUS_NOT_STARTED => 'Tidak Dimulai',
            default => ucfirst($status),
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

    private function authorizeSessionAccess(Request $request, PatrolSession $session): void
    {
        $scope = $this->attendanceScope($request);

        if ($scope !== null && ! in_array((int) $session->unit_id, $scope, true)) {
            abort(403);
        }
    }
}
