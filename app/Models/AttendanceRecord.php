<?php

namespace App\Models;

use App\Support\AttendanceClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class AttendanceRecord extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_DAY_OFF = 'day_off';

    public const STATUS_LABELS = [
        self::STATUS_SCHEDULED => 'Belum Waktunya',
        self::STATUS_PENDING => 'Menunggu Check-in',
        self::STATUS_IN_PROGRESS => 'Sedang Bertugas',
        self::STATUS_COMPLETED => 'Selesai',
        self::STATUS_ABSENT => 'Tidak Hadir',
        self::STATUS_DAY_OFF => 'Libur',
    ];

    /**
     * Statuses that may be produced by an approved correction.
     */
    public const CORRECTABLE_STATUSES = [
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_ABSENT,
        self::STATUS_DAY_OFF,
    ];

    protected $fillable = [
        'schedule_id',
        'schedule_period_id',
        'security_id',
        'security_post_id',
        'shift_id',
        'work_date',
        'security_name',
        'security_registration_number',
        'unit_id',
        'unit_name',
        'security_team_id',
        'security_team_name',
        'security_post_name',
        'shift_name',
        'shift_code',
        'shift_type',
        'shift_start_time',
        'shift_end_time',
        'shift_end_day_offset',
        'scheduled_start_at',
        'scheduled_end_at',
        'post_latitude',
        'post_longitude',
        'setting_gps_radius_meters',
        'setting_check_in_open_minutes',
        'setting_late_tolerance_minutes',
        'setting_early_checkout_tolerance_minutes',
        'setting_checkout_close_minutes',
        'setting_max_gps_accuracy_meters',
        'check_in_at',
        'check_in_captured_at',
        'check_in_latitude',
        'check_in_longitude',
        'check_in_accuracy_meters',
        'check_in_distance_meters',
        'check_in_geofence_valid',
        'check_out_at',
        'check_out_captured_at',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_accuracy_meters',
        'check_out_distance_meters',
        'check_out_geofence_valid',
        'corrected_check_in_at',
        'corrected_check_out_at',
        'corrected_status',
        'status',
        'absent_marked_at',
        'snapshot_locked_at',
        'last_synced_at',
    ];

    protected $casts = [
        'work_date' => 'date',
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'check_in_at' => 'datetime',
        'check_in_captured_at' => 'datetime',
        'check_out_at' => 'datetime',
        'check_out_captured_at' => 'datetime',
        'corrected_check_in_at' => 'datetime',
        'corrected_check_out_at' => 'datetime',
        'absent_marked_at' => 'datetime',
        'snapshot_locked_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'shift_end_day_offset' => 'integer',
        'setting_gps_radius_meters' => 'integer',
        'setting_check_in_open_minutes' => 'integer',
        'setting_late_tolerance_minutes' => 'integer',
        'setting_early_checkout_tolerance_minutes' => 'integer',
        'setting_checkout_close_minutes' => 'integer',
        'setting_max_gps_accuracy_meters' => 'integer',
        'post_latitude' => 'float',
        'post_longitude' => 'float',
        'check_in_latitude' => 'float',
        'check_in_longitude' => 'float',
        'check_in_accuracy_meters' => 'float',
        'check_in_distance_meters' => 'float',
        'check_in_geofence_valid' => 'boolean',
        'check_out_latitude' => 'float',
        'check_out_longitude' => 'float',
        'check_out_accuracy_meters' => 'float',
        'check_out_distance_meters' => 'float',
        'check_out_geofence_valid' => 'boolean',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function schedulePeriod(): BelongsTo
    {
        return $this->belongsTo(SchedulePeriod::class);
    }

    public function security(): BelongsTo
    {
        return $this->belongsTo(Security::class);
    }

    public function securityPost(): BelongsTo
    {
        return $this->belongsTo(SecurityPost::class);
    }

    public function securityTeam(): BelongsTo
    {
        return $this->belongsTo(SecurityTeam::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    public function scopeWorking(Builder $query): Builder
    {
        return $query->where('shift_type', Shift::TYPE_WORKING);
    }

    public function scopeForSecurity(Builder $query, int $securityId): Builder
    {
        return $query->where('security_id', $securityId);
    }

    public function isWorking(): bool
    {
        return $this->shift_type === Shift::TYPE_WORKING;
    }

    public function isDayOff(): bool
    {
        return $this->shift_type === Shift::TYPE_DAY_OFF;
    }

    public function isLocked(): bool
    {
        return $this->snapshot_locked_at !== null;
    }

    public function hasRecordedActivity(): bool
    {
        return $this->check_in_at !== null || $this->check_out_at !== null;
    }

    public function hasAppliedCorrection(): bool
    {
        return $this->corrected_check_in_at !== null
            || $this->corrected_check_out_at !== null
            || $this->corrected_status !== null;
    }

    public function hasPendingCorrection(): bool
    {
        if ($this->relationLoaded('corrections')) {
            return $this->corrections
                ->contains(fn (AttendanceCorrection $correction) => $correction->status === AttendanceCorrection::STATUS_PENDING);
        }

        return $this->corrections()
            ->where('status', AttendanceCorrection::STATUS_PENDING)
            ->exists();
    }

    public function effectiveCheckInAt(): ?Carbon
    {
        return $this->corrected_check_in_at ?? $this->check_in_at;
    }

    public function effectiveCheckOutAt(): ?Carbon
    {
        return $this->corrected_check_out_at ?? $this->check_out_at;
    }

    /**
     * Effective status, combining the stored status, dynamic window and any
     * approved correction override.
     */
    public function effectiveStatus(?Carbon $now = null): string
    {
        if ($this->corrected_status !== null) {
            return $this->corrected_status;
        }

        if ($this->isDayOff()) {
            return self::STATUS_DAY_OFF;
        }

        if (in_array($this->status, [
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_ABSENT,
        ], true)) {
            return $this->status;
        }

        $now ??= AttendanceClock::nowUtc();

        if ($this->isWorking()
            && $this->status === self::STATUS_SCHEDULED
            && $this->effectiveCheckInAt() === null
            && $this->isPastAbsenceDeadline($now)) {
            return self::STATUS_ABSENT;
        }

        if ($this->scheduled_start_at !== null) {
            $opensAt = $this->scheduled_start_at->copy()
                ->subMinutes((int) $this->setting_check_in_open_minutes);

            if ($now->greaterThanOrEqualTo($opensAt)) {
                return self::STATUS_PENDING;
            }
        }

        return self::STATUS_SCHEDULED;
    }

    public function absenceDeadlineAt(): ?Carbon
    {
        if ($this->scheduled_end_at === null) {
            return null;
        }

        return $this->scheduled_end_at->copy()->addHours(24);
    }

    public function isPastAbsenceDeadline(?Carbon $now = null): bool
    {
        $now ??= AttendanceClock::nowUtc();
        $deadline = $this->absenceDeadlineAt();

        return $deadline !== null && $now->greaterThan($deadline);
    }

    public function effectiveStatusLabel(?Carbon $now = null): string
    {
        $status = $this->effectiveStatus($now);

        return self::STATUS_LABELS[$status] ?? $status;
    }

    public function isLate(?Carbon $at = null): bool
    {
        $checkIn = $this->effectiveCheckInAt();

        if ($checkIn === null || $this->scheduled_start_at === null) {
            return false;
        }

        $at ??= $checkIn;

        $limit = $this->scheduled_start_at->copy()
            ->addMinutes((int) $this->setting_late_tolerance_minutes);

        return $at->greaterThan($limit);
    }

    /**
     * Whether the snapshot may be synchronised by the generator.
     */
    public function canSyncSnapshot(): bool
    {
        return ! $this->isLocked()
            && ! $this->hasRecordedActivity()
            && ! $this->hasAppliedCorrection()
            && ! $this->hasPendingCorrection();
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }
}
