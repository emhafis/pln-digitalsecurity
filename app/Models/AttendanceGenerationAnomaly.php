<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceGenerationAnomaly extends Model
{
    use HasFactory;

    public const TYPE_MISSING_SHIFT = 'missing_shift';

    public const TYPE_INVALID_SHIFT = 'invalid_shift';

    public const TYPE_INCOMPLETE_WORKING_TIME = 'incomplete_working_time';

    public const TYPE_MISSING_POST_COORDINATES = 'missing_post_coordinates';

    public const TYPE_INVALID_SECURITY_OR_MEMBERSHIP = 'invalid_security_or_membership';

    public const TYPE_INVALID_SCHEDULE_RELATION = 'invalid_schedule_relation';

    public const TYPE_LABELS = [
        self::TYPE_MISSING_SHIFT => 'Jadwal belum memiliki shift',
        self::TYPE_INVALID_SHIFT => 'Shift tidak valid',
        self::TYPE_INCOMPLETE_WORKING_TIME => 'Waktu shift kerja tidak lengkap',
        self::TYPE_MISSING_POST_COORDINATES => 'Koordinat Pos Jaga belum tersedia',
        self::TYPE_INVALID_SECURITY_OR_MEMBERSHIP => 'Data Satpam atau keanggotaan periode tidak valid',
        self::TYPE_INVALID_SCHEDULE_RELATION => 'Relasi jadwal tidak valid',
    ];

    protected $fillable = [
        'schedule_id',
        'schedule_period_id',
        'security_id',
        'work_date',
        'anomaly_type',
        'details',
        'detected_at',
        'resolved_at',
        'resolved_by',
        'resolution_note',
    ];

    protected $casts = [
        'work_date' => 'date',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
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

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function isActive(): bool
    {
        return $this->resolved_at === null;
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->anomaly_type] ?? $this->anomaly_type;
    }
}
