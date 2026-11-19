<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatrolSession extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_INCOMPLETE = 'incomplete';

    public const STATUS_NOT_STARTED = 'not_started';

    protected $fillable = [
        'schedule_period_id',
        'security_team_id',
        'security_post_id',
        'unit_id',
        'shift_id',
        'work_date',
        'team_name',
        'team_code',
        'post_name',
        'post_code',
        'unit_name',
        'unit_code',
        'shift_name',
        'shift_code',
        'shift_type',
        'shift_start_time',
        'shift_end_time',
        'shift_end_day_offset',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'work_date' => 'date',
        'shift_end_day_offset' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function schedulePeriod(): BelongsTo
    {
        return $this->belongsTo(SchedulePeriod::class, 'schedule_period_id');
    }

    public function securityTeam(): BelongsTo
    {
        return $this->belongsTo(SecurityTeam::class, 'security_team_id');
    }

    public function securityPost(): BelongsTo
    {
        return $this->belongsTo(SecurityPost::class, 'security_post_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(PatrolSessionMember::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(PatrolSessionPoint::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(PatrolScan::class);
    }
}
