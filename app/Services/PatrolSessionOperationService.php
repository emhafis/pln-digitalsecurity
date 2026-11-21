<?php

namespace App\Services;

use App\Models\PatrolScan;
use App\Models\PatrolScanPhoto;
use App\Models\PatrolSession;
use App\Models\PatrolSessionPoint;
use App\Models\Security;
use App\Support\AttendanceClock;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PatrolSessionOperationService
{
    public const MAX_INCIDENT_PHOTOS = 5;

    public const INCIDENT_PHOTO_MAX_BYTES = 1536 * 1024; // ~1.5MB per photo

    /**
     * @return array{session: PatrolSession, started_now: bool}
     */
    public function start(PatrolSession $session, Security $security, ?Carbon $now = null): array
    {
        $now ??= now();

        return DB::transaction(function () use ($session, $security, $now) {
            $locked = PatrolSession::query()
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertMember($locked, $security);

            if (! $locked->members()->exists() || ! $locked->points()->exists()) {
                throw ValidationException::withMessages([
                    'patrol' => 'Sesi patroli tidak memiliki anggota atau titik wajib yang valid.',
                ]);
            }

            if ($locked->status === PatrolSession::STATUS_PENDING) {
                $this->assertCanStartNow($locked, $now);

                $locked->forceFill([
                    'status' => PatrolSession::STATUS_ACTIVE,
                    'started_at' => $now,
                ])->save();

                return ['session' => $locked->refresh(), 'started_now' => true];
            }

            if ($locked->status === PatrolSession::STATUS_ACTIVE) {
                return ['session' => $locked, 'started_now' => false];
            }

            throw ValidationException::withMessages([
                'patrol' => 'Sesi patroli tidak dapat dimulai pada status saat ini.',
            ]);
        });
    }

    /**
     * Compute shift start window from session snapshot (never master shift).
     * Start is inclusive, end is exclusive: start <= now < end.
     *
     * @return array{can_start_now:bool,state:string,message:?string,start_at_utc:?Carbon,end_at_utc:?Carbon,start_at_wib:?string,end_at_wib:?string,shift_time_label:string}
     */
    public function startWindowInfo(PatrolSession $session, ?Carbon $now = null): array
    {
        $now ??= now();
        $shiftTimeLabel = $this->formatShiftTimeLabel($session->shift_start_time, $session->shift_end_time);

        $base = [
            'can_start_now' => false,
            'state' => 'invalid',
            'message' => 'Data waktu shift sesi tidak lengkap atau tidak valid. Sesi tidak dapat dimulai.',
            'start_at_utc' => null,
            'end_at_utc' => null,
            'start_at_wib' => null,
            'end_at_wib' => null,
            'shift_time_label' => $shiftTimeLabel,
        ];

        try {
            $workDate = $session->work_date?->toDateString();
            $startTime = $session->shift_start_time;
            $endTime = $session->shift_end_time;

            if (! $workDate || $startTime === null || $startTime === '' || $endTime === null || $endTime === '') {
                return $base;
            }

            $offset = (int) ($session->shift_end_day_offset ?? 0);
            $startAt = AttendanceClock::scheduledStart($workDate, (string) $startTime);
            $endAt = AttendanceClock::scheduledEnd($workDate, (string) $endTime, $offset);
        } catch (\Throwable) {
            return $base;
        }

        if (! $endAt->greaterThan($startAt)) {
            return $base;
        }

        $startAtWib = $startAt->copy()->setTimezone(AttendanceClock::OPERATIONAL_TIMEZONE)->format('d M Y, H:i').' WIB';
        $endAtWib = $endAt->copy()->setTimezone(AttendanceClock::OPERATIONAL_TIMEZONE)->format('d M Y, H:i').' WIB';

        $canStartNow = $now->greaterThanOrEqualTo($startAt) && $now->lessThan($endAt);

        $state = $canStartNow ? 'open' : ($now->lessThan($startAt) ? 'before' : 'after');
        $message = null;
        if ($state === 'before') {
            $message = 'Sesi belum dapat dimulai. Sesi dijadwalkan mulai pada '.$startAtWib.'.';
        } elseif ($state === 'after') {
            $message = 'Waktu mulai sesi sudah berakhir.';
        }

        return [
            'can_start_now' => $canStartNow,
            'state' => $state,
            'message' => $message,
            'start_at_utc' => $startAt,
            'end_at_utc' => $endAt,
            'start_at_wib' => $startAtWib,
            'end_at_wib' => $endAtWib,
            'shift_time_label' => $shiftTimeLabel,
        ];
    }

    private function assertCanStartNow(PatrolSession $session, Carbon $now): void
    {
        $window = $this->startWindowInfo($session, $now);

        if ($window['state'] === 'invalid') {
            throw ValidationException::withMessages([
                'patrol' => $window['message'] ?? 'Data waktu shift sesi tidak lengkap atau tidak valid. Sesi tidak dapat dimulai.',
            ]);
        }

        if (! $window['can_start_now']) {
            throw ValidationException::withMessages([
                'patrol' => $window['message'] ?? 'Sesi patroli tidak dapat dimulai pada waktu ini.',
            ]);
        }
    }

    private function formatShiftTimeLabel(mixed $start, mixed $end): string
    {
        $s = $start ? substr((string) $start, 0, 5) : '';
        $e = $end ? substr((string) $end, 0, 5) : '';

        return $s && $e ? $s.' - '.$e : ($s ?: $e ?: '-');
    }

    /**
     * Preview QR without creating any scan/progress/photo records.
     * PATROL-14: titik yang sudah pernah dipindai tetap boleh dipreview.
     *
     * @return array{session_id:int, point:array<string,mixed>}
     */
    public function preview(PatrolSession $session, Security $security, string $qrCode): array
    {
        return DB::transaction(function () use ($session, $security, $qrCode) {
            $locked = PatrolSession::query()
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertMember($locked, $security);
            $this->assertScannable($locked);

            $point = $this->resolvePointForQr($locked, $qrCode);
            $scanCount = PatrolScan::query()
                ->where('patrol_session_id', $locked->id)
                ->where('patrol_point_id', $point->patrol_point_id)
                ->count();

            return [
                'session_id' => (int) $locked->id,
                'point' => [
                    'id' => (int) $point->id,
                    'patrol_point_id' => (int) $point->patrol_point_id,
                    'name' => $point->patrol_point_name,
                    'sequence' => (int) $point->sequence,
                    'qr_code' => $point->patrol_point_qr_code,
                    'already_scanned' => $scanCount > 0,
                    'scan_count' => (int) $scanCount,
                ],
            ];
        }, 3);
    }

    /**
     * Persist inspection result after explicit confirmation. Re-validates everything atomically.
     * PATROL-14: multi-scan per titik, progres by unique fulfilled points, idempotency-key guard.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, UploadedFile>|null  $photos
     * @return array{scan: PatrolScan, session: PatrolSession, completed: bool, idempotent: bool}
     */
    public function submit(
        PatrolSession $session,
        Security $security,
        array $payload,
        ?array $photos = null,
        ?Carbon $now = null
    ): array {
        $now ??= now();
        $qrCode = (string) ($payload['qr_code'] ?? '');
        $idempotencyKey = $this->normalizeIdempotencyKey($payload['idempotency_key'] ?? null);
        $photos = is_array($photos) ? array_values($photos) : [];

        [$hasIncident, $incidentDescription] = $this->resolveIncidentPayload($payload, $photos);
        $coords = $this->validateCoordinates($payload);

        if ($idempotencyKey !== null) {
            $existing = $this->findIdempotentScan($session, $security, $idempotencyKey);
            if ($existing !== null) {
                return [
                    'scan' => $existing->loadMissing('photos'),
                    'session' => $session->fresh(),
                    'completed' => $session->fresh()?->status === PatrolSession::STATUS_COMPLETED,
                    'idempotent' => true,
                ];
            }
        }

        $storedPhotoPaths = [];
        if ($hasIncident && ! empty($photos)) {
            $storedPhotoPaths = $this->storeIncidentPhotos($photos, $security->full_name);
        }

        try {
            return DB::transaction(function () use ($session, $security, $qrCode, $coords, $hasIncident, $incidentDescription, $storedPhotoPaths, $now, $idempotencyKey) {
                $locked = PatrolSession::query()
                    ->whereKey($session->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertMember($locked, $security);
                $this->assertScannable($locked);

                if ($idempotencyKey !== null) {
                    $existing = PatrolScan::query()
                        ->where('patrol_session_id', $locked->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();
                    if ($existing !== null) {
                        if ((int) $existing->security_id !== (int) $security->id) {
                            throw ValidationException::withMessages([
                                'idempotency_key' => 'Permintaan scan tidak valid.',
                            ]);
                        }

                        return [
                            'scan' => $existing->loadMissing('photos'),
                            'session' => $locked->refresh(),
                            'completed' => $locked->status === PatrolSession::STATUS_COMPLETED,
                            'idempotent' => true,
                        ];
                    }
                }

                $point = $this->resolvePointForQr($locked, $qrCode);

                $scan = PatrolScan::query()->create([
                    'patrol_session_id' => $locked->id,
                    'patrol_point_id' => $point->patrol_point_id,
                    'security_id' => $security->id,
                    'patrol_point_name' => $point->patrol_point_name,
                    'patrol_point_qr_code' => $point->patrol_point_qr_code,
                    'security_name' => (string) $security->full_name,
                    'security_registration_number' => $security->registration_number,
                    'scanned_at' => $now,
                    'latitude' => $coords['latitude'],
                    'longitude' => $coords['longitude'],
                    'accuracy' => $coords['accuracy'],
                    'has_incident' => $hasIncident,
                    'incident_description' => $incidentDescription,
                    'incident_photo_path' => $storedPhotoPaths[0] ?? null,
                    'idempotency_key' => $idempotencyKey,
                ]);

                foreach ($storedPhotoPaths as $index => $path) {
                    PatrolScanPhoto::query()->create([
                        'patrol_scan_id' => $scan->id,
                        'photo_path' => $path,
                        'sequence' => $index + 1,
                    ]);
                }

                $completed = $this->completeIfFinished($locked, $now);

                return [
                    'scan' => $scan->load('photos'),
                    'session' => $locked->refresh(),
                    'completed' => $completed,
                    'idempotent' => false,
                ];
            }, 3);
        } catch (UniqueConstraintViolationException|QueryException $e) {
            $this->cleanupPhotos($storedPhotoPaths);
            if ($idempotencyKey !== null && $this->isIdempotencyDuplicateKey($e)) {
                $existing = PatrolScan::query()
                    ->where('patrol_session_id', $session->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing !== null && (int) $existing->security_id === (int) $security->id) {
                    $fresh = $session->fresh();

                    return [
                        'scan' => $existing->loadMissing('photos'),
                        'session' => $fresh,
                        'completed' => $fresh?->status === PatrolSession::STATUS_COMPLETED,
                        'idempotent' => true,
                    ];
                }
            }

            throw $e;
        } catch (\Throwable $e) {
            $this->cleanupPhotos($storedPhotoPaths);
            throw $e;
        }
    }

    /**
     * Backward-compatible wrapper (direct confirmed submission).
     *
     * @param  array<string, mixed>  $payload
     * @return array{scan: PatrolScan, session: PatrolSession, completed: bool, duplicate: bool, idempotent: bool}
     */
    public function scan(
        PatrolSession $session,
        Security $security,
        array $payload,
        ?UploadedFile $incidentPhoto = null,
        ?Carbon $now = null
    ): array {
        $normalized = $payload;
        $hasIncident = filter_var($payload['has_incident'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $normalized['condition'] = $hasIncident ? 'incident' : 'safe';

        $photos = [];
        if ($incidentPhoto !== null) {
            $photos[] = $incidentPhoto;
        } elseif (! empty($payload['photos']) && is_array($payload['photos'])) {
            $photos = array_values(array_filter($payload['photos'], fn ($p) => $p instanceof UploadedFile));
        }

        $result = $this->submit($session, $security, $normalized, $photos, $now);
        $result['duplicate'] = $result['idempotent'] ?? false;

        return $result;
    }

    private function normalizeIdempotencyKey(mixed $key): ?string
    {
        if ($key === null) {
            return null;
        }
        $key = trim((string) $key);
        if ($key === '' || strlen($key) > 64) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Kunci idempoten tidak valid.',
            ]);
        }

        return $key;
    }

    private function findIdempotentScan(PatrolSession $session, Security $security, string $idempotencyKey): ?PatrolScan
    {
        $scan = PatrolScan::query()
            ->where('patrol_session_id', $session->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($scan !== null && (int) $scan->security_id !== (int) $security->id) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Permintaan scan tidak valid.',
            ]);
        }

        return $scan;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, UploadedFile>  $photos
     * @return array{0: bool, 1: ?string}
     */
    private function resolveIncidentPayload(array $payload, array $photos): array
    {
        $condition = $payload['condition'] ?? null;
        if ($condition === null) {
            $condition = filter_var($payload['has_incident'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'incident' : 'safe';
        }

        if (! in_array($condition, ['safe', 'incident'], true)) {
            throw ValidationException::withMessages([
                'condition' => 'Kondisi titik patroli tidak valid.',
            ]);
        }

        $description = array_key_exists('incident_description', $payload) ? $payload['incident_description'] : null;
        if (is_string($description)) {
            $description = trim($description);
            $description = $description === '' ? null : $description;
        } else {
            $description = null;
        }

        $hasIncident = $condition === 'incident';

        if (! $hasIncident) {
            if ($description !== null || ! empty($photos)) {
                throw ValidationException::withMessages([
                    'incident_description' => 'Deskripsi atau foto insiden hanya dapat dikirim jika ada kejadian.',
                ]);
            }

            return [false, null];
        }

        if ($description === null) {
            throw ValidationException::withMessages([
                'incident_description' => 'Deskripsi kejadian wajib diisi jika ada insiden.',
            ]);
        }

        if (count($photos) > self::MAX_INCIDENT_PHOTOS) {
            throw ValidationException::withMessages([
                'photos' => 'Jumlah foto insiden maksimal '.self::MAX_INCIDENT_PHOTOS.' foto.',
            ]);
        }

        return [true, $description];
    }

    private function assertScannable(PatrolSession $locked): void
    {
        if ($locked->status !== PatrolSession::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'patrol' => $locked->status === PatrolSession::STATUS_PENDING
                    ? 'Sesi patroli belum dimulai.'
                    : 'Sesi patroli tidak dapat menerima scan pada status saat ini.',
            ]);
        }
    }

    private function resolvePointForQr(PatrolSession $session, string $qrCode): PatrolSessionPoint
    {
        $point = PatrolSessionPoint::query()
            ->where('patrol_session_id', $session->id)
            ->where('patrol_point_qr_code', $qrCode)
            ->first();

        if ($point === null) {
            throw ValidationException::withMessages([
                'qr_code' => 'Kode QR tidak valid atau bukan milik sesi patroli ini.',
            ]);
        }

        return $point;
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     * @return array<int, string>
     */
    private function storeIncidentPhotos(array $photos, ?string $personName = null): array
    {
        $paths = [];

        foreach ($photos as $file) {
            if (! $file instanceof UploadedFile) {
                $this->cleanupPhotos($paths);
                throw ValidationException::withMessages(['photos' => 'File foto insiden tidak valid.']);
            }

            $this->validateIncidentPhoto($file);
            $ext = \App\Support\UploadFilename::extensionFromUpload($file, 'jpg');
            $filename = \App\Support\UploadFilename::dated([
                'PATROLI',
                \App\Support\UploadFilename::short($personName ?? '', 12),
            ], $ext);
            $directory = 'patrol-incidents';
            $path = $directory.'/'.$filename;
            $stored = Storage::disk('public')->putFileAs($directory, $file, $filename);

            if ($stored === false) {
                $this->cleanupPhotos($paths);
                throw ValidationException::withMessages(['photos' => 'Gagal menyimpan foto insiden.']);
            }

            $paths[] = $path;
        }

        return $paths;
    }

    private function validateIncidentPhoto(UploadedFile $file): void
    {
        $mime = (string) $file->getMimeType();
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages([
                'photos' => 'Format foto insiden tidak valid. Gunakan JPG, PNG, atau WebP.',
            ]);
        }

        if ($file->getSize() === false || $file->getSize() > self::INCIDENT_PHOTO_MAX_BYTES) {
            throw ValidationException::withMessages([
                'photos' => 'Ukuran foto insiden melebihi batas yang diizinkan.',
            ]);
        }
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function cleanupPhotos(array $paths): void
    {
        foreach ($paths as $path) {
if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
        }
    }

    /**
     * PATROL-14: selesai jika semua titik wajib unik memiliki >=1 scan (berdasarkan state DB terbaru).
     * Sesi tanpa titik wajib tidak pernah auto-complete.
     */
    private function completeIfFinished(PatrolSession $session, ?Carbon $now = null): bool
    {
        $now ??= now();

        $required = (int) $session->points()->count();
        if ($required <= 0) {
            return false;
        }

        $fulfilled = (int) $session->scans()
            ->distinct()
            ->count('patrol_point_id');

        if ($fulfilled >= $required) {
            $session->forceFill([
                'status' => PatrolSession::STATUS_COMPLETED,
                'ended_at' => $session->ended_at ?? $now,
            ])->save();

            return true;
        }

        return false;
    }

    private function assertMember(PatrolSession $session, Security $security): void
    {
        $isMember = $session->members()
            ->where('security_id', $security->id)
            ->exists();

        if (! $isMember) {
            abort(403, 'Anda tidak terdaftar sebagai anggota sesi patroli ini.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{latitude: ?string, longitude: ?string, accuracy: ?string}
     */
    private function validateCoordinates(array $payload): array
    {
        $lat = $payload['latitude'] ?? null;
        $lng = $payload['longitude'] ?? null;
        $acc = $payload['accuracy'] ?? null;

        $hasAny = ($lat !== null && $lat !== '') || ($lng !== null && $lng !== '') || ($acc !== null && $acc !== '');
        if (! $hasAny) {
            return ['latitude' => null, 'longitude' => null, 'accuracy' => null];
        }

        if ($lat === null || $lat === '' || $lng === null || $lng === '' || ! is_numeric($lat) || ! is_numeric($lng)) {
            throw ValidationException::withMessages([
                'location' => 'Koordinat lokasi tidak valid.',
            ]);
        }

        $latF = (float) $lat;
        $lngF = (float) $lng;
        if ($latF < -90 || $latF > 90 || $lngF < -180 || $lngF > 180) {
            throw ValidationException::withMessages([
                'location' => 'Koordinat lokasi berada di luar rentang yang valid.',
            ]);
        }

        $accVal = null;
        if ($acc !== null && $acc !== '') {
            if (! is_numeric($acc)) {
                throw ValidationException::withMessages([
                    'accuracy' => 'Akurasi lokasi tidak valid.',
                ]);
            }
            $accF = (float) $acc;
            if ($accF < 0) {
                throw ValidationException::withMessages([
                    'accuracy' => 'Akurasi lokasi tidak valid.',
                ]);
            }
            $accVal = number_format($accF, 2, '.', '');
        }

        return [
            'latitude' => number_format($latF, 7, '.', ''),
            'longitude' => number_format($lngF, 7, '.', ''),
            'accuracy' => $accVal,
        ];
    }

    private function isIdempotencyDuplicateKey(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'patrol_scans_session_idempotency_unique')
            || ((str_contains($message, 'UNIQUE constraint failed') || str_contains($message, 'Duplicate entry') || str_contains($message, 'Integrity constraint violation: 1062'))
                && str_contains($message, 'idempotency'));
    }
}
