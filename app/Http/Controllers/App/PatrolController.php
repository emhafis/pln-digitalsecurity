<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\PatrolScan;
use App\Models\PatrolScanPhoto;
use App\Models\PatrolSession;
use App\Models\Security;
use App\Services\PatrolSessionOperationService;
use App\Support\AttendanceClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class PatrolController extends Controller
{
    public function index(Request $request, PatrolSessionOperationService $service): Response
    {
        $security = $this->resolveSecurity($request);
        $today = AttendanceClock::todayWib();
        $now = now();

        $sessions = PatrolSession::query()
            ->whereHas('members', fn ($q) => $q->where('security_id', $security->id))
            ->whereIn('status', [
                PatrolSession::STATUS_PENDING,
                PatrolSession::STATUS_ACTIVE,
                PatrolSession::STATUS_COMPLETED,
                PatrolSession::STATUS_INCOMPLETE,
                PatrolSession::STATUS_NOT_STARTED,
            ])
            ->withCount(['points as required_points'])
            ->withCount(['scans as total_scans'])
            ->withCount(['scans as fulfilled_points' => function ($q) {
                $q->select(\DB::raw('count(distinct patrol_point_id)'));
            }])
            ->orderBy('work_date')
            ->orderBy('shift_start_time')
            ->orderBy('id')
            ->get();

        $mapped = $sessions->map(function (PatrolSession $session) use ($today, $now, $service) {
            $required = (int) $session->required_points;
            $fulfilled = (int) $session->fulfilled_points;
            $progress = $required > 0 ? (int) round(($fulfilled / $required) * 100) : 0;
            [$displayStatus, $displayLabel] = $this->displayStatus($session, $service->startWindowInfo($session, $now));

            return [
                'id' => $session->id,
                'post_name' => $session->post_name,
                'team_name' => $session->team_name,
                'work_date' => $session->work_date?->toDateString(),
                'work_date_label' => $session->work_date?->format('d M Y'),
                'shift_name' => $session->shift_name,
                'shift_time' => $this->formatShiftTime($session->shift_start_time, $session->shift_end_time),
                'status' => $session->status,
                'display_status' => $displayStatus,
                'display_status_label' => $displayLabel,
                'required_points' => $required,
                'fulfilled_points' => $fulfilled,
                'scanned_points' => $fulfilled,
                'total_scans' => (int) $session->total_scans,
                'progress' => $progress,
                'is_today' => $session->work_date?->toDateString() === $today,
                'started_at' => $session->started_at?->toIso8601String(),
                'ended_at' => $session->ended_at?->toIso8601String(),
            ];
        });

        $todaySessions = $mapped->filter(fn ($s) => $s['is_today'])->values();
        $otherSessionsAll = $mapped->reject(fn ($s) => $s['is_today'])->sortByDesc('work_date')->values();

        $statusLabels = [
            'pending' => 'Menunggu',
            'ready' => 'Siap Dimulai',
            'active' => 'Berlangsung',
            'missed' => 'Terlewat',
            'completed' => 'Selesai',
            'incomplete' => 'Tidak Lengkap',
        ];

        $otherStatusOptions = collect($statusLabels)
            ->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'count' => $otherSessionsAll->where('display_status', $key)->count(),
            ])
            ->filter(fn (array $option) => $option['count'] > 0)
            ->values();

        $status = trim((string) $request->query('status', ''));
        if ($status !== '' && ! array_key_exists($status, $statusLabels)) {
            $status = '';
        }

        $filtered = $status === ''
            ? $otherSessionsAll
            : $otherSessionsAll->filter(fn (array $s) => $s['display_status'] === $status)->values();

        $perPage = 10;
        $total = $filtered->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) $request->query('page', 1)), $lastPage);
        $otherSessions = $filtered->slice(($page - 1) * $perPage, $perPage)->values();

        return Inertia::render('App/Patrol/Index', [
            'todaySessions' => $todaySessions,
            'otherSessions' => $otherSessions,
            'otherSessionsMeta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'total' => $total,
                'has_more' => $page < $lastPage,
                'status' => $status,
            ],
            'otherStatusOptions' => $otherStatusOptions,
            'today' => $today,
        ]);
    }

    /**
     * @param  array{state:string}  $window
     * @return array{0:string,1:string}
     */
    private function displayStatus(PatrolSession $session, array $window): array
    {
        return match ($session->status) {
            PatrolSession::STATUS_ACTIVE => ['active', 'Berlangsung'],
            PatrolSession::STATUS_COMPLETED => ['completed', 'Selesai'],
            PatrolSession::STATUS_INCOMPLETE => ['incomplete', 'Tidak Lengkap'],
            PatrolSession::STATUS_PENDING, PatrolSession::STATUS_NOT_STARTED => match ($window['state'] ?? 'invalid') {
                'before' => ['pending', 'Menunggu'],
                'open' => ['ready', 'Siap Dimulai'],
                default => ['missed', 'Terlewat'],
            },
            default => ['missed', 'Terlewat'],
        };
    }

    public function show(Request $request, PatrolSession $patrolSession, PatrolSessionOperationService $service): Response
    {
        $security = $this->resolveSecurity($request);
        $this->authorizeMember($patrolSession, $security);

        $patrolSession->load([
            'points' => fn ($q) => $q->orderBy('sequence'),
            'scans' => fn ($q) => $q->with('photos')->orderBy('scanned_at'),
        ]);

        $scansByPoint = $patrolSession->scans->groupBy('patrol_point_id');
        $required = $patrolSession->points->count();
        $fulfilled = $scansByPoint->count();
        $totalScans = $patrolSession->scans->count();
        $progress = $required > 0 ? (int) round(($fulfilled / $required) * 100) : 0;

        $points = $patrolSession->points->map(function ($point) use ($scansByPoint, $patrolSession) {
            $pointScans = $scansByPoint->get($point->patrol_point_id, collect())->sortBy('scanned_at')->values();
            $latest = $pointScans->last();
            $scanCount = $pointScans->count();
            $latestPhotos = $latest ? $this->scanPhotoPayloads($patrolSession, $latest) : [];
            $hasIncidentAny = $pointScans->contains(fn ($s) => (bool) $s->has_incident);

            return [
                'id' => $point->id,
                'patrol_point_id' => $point->patrol_point_id,
                'name' => $point->patrol_point_name,
                'sequence' => $point->sequence,
                'scanned' => $scanCount > 0,
                'fulfilled' => $scanCount > 0,
                'scan_count' => $scanCount,
                'scanned_at' => $latest?->scanned_at?->toIso8601String(),
                'scanned_at_label' => $latest ? AttendanceClock::formatWib($latest->scanned_at) : null,
                'scanned_by_name' => $latest?->security_name,
                'scanned_by_registration_number' => $latest?->security_registration_number,
                'latitude' => $latest?->latitude,
                'longitude' => $latest?->longitude,
                'accuracy' => $latest?->accuracy,
                'has_incident' => $hasIncidentAny,
                'point_status' => $scanCount > 0 ? ($latest->has_incident ? 'incident' : 'safe') : 'not_scanned',
                'incident_description' => $latest?->incident_description,
                'photo_url' => $latest?->incident_photo_path
                    ? '/storage/' . $latest->incident_photo_path
                    : null,
                'photos' => $latestPhotos,
            ];
        })->values();

        $lastScan = $patrolSession->scans->sortByDesc('scanned_at')->first();
        $window = $service->startWindowInfo($patrolSession);

        return Inertia::render('App/Patrol/Show', [
            'session' => [
                'id' => $patrolSession->id,
                'post_name' => $patrolSession->post_name,
                'team_name' => $patrolSession->team_name,
                'unit_name' => $patrolSession->unit_name,
                'work_date' => $patrolSession->work_date?->toDateString(),
                'work_date_label' => $patrolSession->work_date?->format('d M Y'),
                'shift_name' => $patrolSession->shift_name,
                'shift_time' => $this->formatShiftTime($patrolSession->shift_start_time, $patrolSession->shift_end_time),
                'status' => $patrolSession->status,
                'started_at' => $patrolSession->started_at?->toIso8601String(),
                'ended_at' => $patrolSession->ended_at?->toIso8601String(),
                'required_points' => $required,
                'fulfilled_points' => $fulfilled,
                'scanned_points' => $fulfilled,
                'total_scans' => $totalScans,
                'progress' => $progress,
                'start_window' => [
                    'can_start_now' => $window['can_start_now'],
                    'state' => $window['state'],
                    'message' => $window['message'],
                    'start_at_wib' => $window['start_at_wib'],
                    'end_at_wib' => $window['end_at_wib'],
                    'shift_time_label' => $window['shift_time_label'],
                ],
            ],
            'points' => $points,
            'last_scan' => $lastScan ? [
                'patrol_point_name' => $lastScan->patrol_point_name,
                'scanned_at' => $lastScan->scanned_at?->toIso8601String(),
                'scanned_at_label' => AttendanceClock::formatWib($lastScan->scanned_at),
                'has_incident' => (bool) $lastScan->has_incident,
            ] : null,
        ]);
    }

    public function scanPage(Request $request, PatrolSession $patrolSession, PatrolSessionOperationService $service): Response|RedirectResponse
    {
        $security = $this->resolveSecurity($request);
        $this->authorizeMember($patrolSession, $security);

        if (in_array($patrolSession->status, [PatrolSession::STATUS_COMPLETED, PatrolSession::STATUS_INCOMPLETE, PatrolSession::STATUS_NOT_STARTED], true)) {
            return redirect()->route('app.patrol.show', $patrolSession)
                ->with('error', 'Sesi patroli tidak dapat menerima scan pada status saat ini.');
        }

        $patrolSession->loadCount(['points as required_points']);
        $fulfilled = (int) $patrolSession->scans()->distinct()->count('patrol_point_id');
        $totalScans = (int) $patrolSession->scans()->count();
        $window = $service->startWindowInfo($patrolSession);

        return Inertia::render('App/Patrol/Scan', [
            'session' => [
                'id' => $patrolSession->id,
                'post_name' => $patrolSession->post_name,
                'team_name' => $patrolSession->team_name,
                'unit_name' => $patrolSession->unit_name,
                'work_date_label' => $patrolSession->work_date?->format('d M Y'),
                'shift_name' => $patrolSession->shift_name,
                'shift_time' => $this->formatShiftTime($patrolSession->shift_start_time, $patrolSession->shift_end_time),
                'status' => $patrolSession->status,
                'required_points' => (int) $patrolSession->required_points,
                'fulfilled_points' => $fulfilled,
                'scanned_points' => $fulfilled,
                'total_scans' => $totalScans,
                'start_window' => [
                    'can_start_now' => $window['can_start_now'],
                    'state' => $window['state'],
                    'message' => $window['message'],
                    'start_at_wib' => $window['start_at_wib'],
                    'end_at_wib' => $window['end_at_wib'],
                    'shift_time_label' => $window['shift_time_label'],
                ],
            ],
            'routes' => [
                'show' => route('app.patrol.show', $patrolSession),
                'start' => route('app.patrol.start', $patrolSession),
                'preview' => route('app.patrol.preview', $patrolSession),
                'submit' => route('app.patrol.scan', $patrolSession),
            ],
            'max_photos' => PatrolSessionOperationService::MAX_INCIDENT_PHOTOS,
        ]);
    }

    public function start(Request $request, PatrolSession $patrolSession, PatrolSessionOperationService $service): RedirectResponse|JsonResponse
    {
        $wantsJson = $request->expectsJson() || $request->ajax();

        try {
            $security = $this->resolveSecurity($request);
            $result = $service->start($patrolSession, $security);
            $session = $result['session'];

            $payload = [
                'message' => $result['started_now'] ? 'Sesi patroli dimulai.' : 'Sesi patroli sudah aktif.',
                'session' => [
                    'id' => $session->id,
                    'status' => $session->status,
                    'started_at' => $session->started_at?->toIso8601String(),
                ],
            ];

            if ($wantsJson) {
                return response()->json($payload);
            }

            return redirect()->back()->with('success', $payload['message']);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->filter()->implode(' ');
            if ($message === '') {
                $message = 'Gagal memulai sesi patroli.';
            }

            if ($wantsJson) {
                return response()->json([
                    'message' => $message,
                    'errors' => $e->errors(),
                ], $e->status);
            }

            throw $e;
        } catch (HttpExceptionInterface $e) {
            $status = $e->getStatusCode();
            $message = $e->getMessage() ?: 'Gagal memulai sesi patroli.';

            if ($wantsJson) {
                return response()->json(['message' => $message], $status);
            }

            throw $e;
        } catch (Throwable $e) {
            report($e);

            $message = 'Terjadi kesalahan saat memulai sesi patroli. Coba lagi beberapa saat.';

            if ($wantsJson) {
                return response()->json(['message' => $message], 500);
            }

            return redirect()->back()->with('error', $message);
        }
    }

    public function preview(Request $request, PatrolSession $patrolSession, PatrolSessionOperationService $service): JsonResponse
    {
        $validated = $request->validate([
            'qr_code' => ['required', 'uuid'],
        ]);

        try {
            $security = $this->resolveSecurity($request);
            $result = $service->preview($patrolSession, $security, $validated['qr_code']);

            return response()->json([
                'message' => $result['point']['already_scanned']
                    ? 'Titik sudah pernah dipindai. Anda tetap dapat menambah scan baru.'
                    : 'QR valid. Konfirmasi hasil pemeriksaan sebelum dikirim.',
                'preview' => $result,
            ]);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->filter()->implode(' ');

            return response()->json([
                'message' => $message !== '' ? $message : 'QR tidak dapat dipreview.',
                'errors' => $e->errors(),
            ], $e->status);
        } catch (HttpExceptionInterface $e) {
            return response()->json(['message' => $e->getMessage() ?: 'QR tidak dapat dipreview.'], $e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Terjadi kesalahan saat memvalidasi QR.'], 500);
        }
    }

    public function scan(Request $request, PatrolSession $patrolSession, PatrolSessionOperationService $service): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'qr_code' => ['required', 'uuid'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'condition' => ['nullable', 'in:safe,incident'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'has_incident' => ['nullable', 'boolean'],
            'incident_description' => ['nullable', 'string'],
            'photos' => ['nullable', 'array', 'max:'.PatrolSessionOperationService::MAX_INCIDENT_PHOTOS],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'incident_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $photos = $request->file('photos', []);
        if (empty($photos) && $request->hasFile('incident_photo')) {
            $photos = [$request->file('incident_photo')];
        }

        try {
            $security = $this->resolveSecurity($request);
            $result = $service->submit($patrolSession, $security, $validated, $photos);
            $scan = $result['scan'];
            $session = $result['session'];
            $photosPayload = $this->scanPhotoPayloads($session, $scan);

            $required = (int) $session->points()->count();
            $fulfilled = (int) $session->scans()->distinct()->count('patrol_point_id');
            $totalScans = (int) $session->scans()->count();
            $pointScanCount = (int) $session->scans()->where('patrol_point_id', $scan->patrol_point_id)->count();

            $message = $result['completed']
                ? 'Scan berhasil. Sesi patroli selesai.'
                : ($result['idempotent'] ? 'Scan sudah tercatat sebelumnya.' : 'Scan berhasil dicatat.');

            $payload = [
                'message' => $message,
                'scan' => [
                    'id' => $scan->id,
                    'patrol_point_id' => $scan->patrol_point_id,
                    'scanned_at' => $scan->scanned_at?->toIso8601String(),
                    'scanned_at_label' => AttendanceClock::formatWib($scan->scanned_at),
                    'has_incident' => (bool) $scan->has_incident,
                    'incident_description' => $scan->incident_description,
                    'photos' => $photosPayload,
                    'point_scan_count' => $pointScanCount,
                ],
                'session' => [
                    'id' => $session->id,
                    'status' => $session->status,
                    'ended_at' => $session->ended_at?->toIso8601String(),
                    'required_points' => $required,
                    'fulfilled_points' => $fulfilled,
                    'scanned_points' => $fulfilled,
                    'total_scans' => $totalScans,
                    'progress' => $required > 0 ? (int) round(($fulfilled / $required) * 100) : 0,
                ],
                'completed' => $result['completed'],
                'idempotent' => $result['idempotent'],
            ];

            if ($request->expectsJson()) {
                return response()->json($payload);
            }

            return redirect()->route('app.patrol.show', $patrolSession)->with('success', $payload['message']);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->filter()->implode(' ');
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message !== '' ? $message : 'Hasil pemeriksaan gagal dikirim.',
                    'errors' => $e->errors(),
                ], $e->status);
            }
            throw $e;
        } catch (HttpExceptionInterface $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage() ?: 'Hasil pemeriksaan gagal dikirim.'], $e->getStatusCode());
            }
            throw $e;
        } catch (Throwable $e) {
            report($e);
            $message = 'Terjadi kesalahan saat mengirim hasil pemeriksaan. Tidak ada data yang disimpan.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 500);
            }

            return redirect()->back()->with('error', $message);
        }
    }

    public function incidentPhoto(Request $request, PatrolSession $patrolSession, PatrolScan $scan): StreamedResponse
    {
        if ((int) $scan->patrol_session_id !== (int) $patrolSession->id || ! $scan->incident_photo_path) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($scan->incident_photo_path)) {
            abort(404);
        }

        return Storage::disk('public')->response($scan->incident_photo_path);
    }

    public function incidentPhotoItem(Request $request, PatrolSession $patrolSession, PatrolScan $scan, PatrolScanPhoto $photo): StreamedResponse
    {
        if ((int) $scan->patrol_session_id !== (int) $patrolSession->id || (int) $photo->patrol_scan_id !== (int) $scan->id) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($photo->photo_path)) {
            abort(404);
        }

        return Storage::disk('public')->response($photo->photo_path);
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
                'url' => '/storage/' . $photo->photo_path,
                'sequence' => (int) $photo->sequence,
                'legacy' => false,
            ];
        }

        if (! $hasNew && $scan->incident_photo_path) {
            $photos[] = [
                'id' => 0,
                'url' => '/storage/' . $scan->incident_photo_path,
                'sequence' => 1,
                'legacy' => true,
            ];
        }

        return $photos;
    }

    private function resolveSecurity(Request $request): Security
    {
        $security = Security::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($security === null) {
            abort(403, 'Akun Anda belum terhubung dengan data Satpam.');
        }

        return $security;
    }

    private function authorizeMember(PatrolSession $session, Security $security): void
    {
        $isMember = $session->members()->where('security_id', $security->id)->exists();
        if (! $isMember) {
            abort(403, 'Anda tidak terdaftar sebagai anggota sesi patroli ini.');
        }
    }

    private function formatShiftTime($start, $end): string
    {
        $s = $start ? substr((string) $start, 0, 5) : '';
        $e = $end ? substr((string) $end, 0, 5) : '';
        if ($s && $e) {
            return $s.' - '.$e;
        }

        return $s ?: $e ?: '-';
    }
}
