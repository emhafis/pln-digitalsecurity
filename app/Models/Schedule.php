<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class Schedule extends Model
{
    use HasFactory;

    public const SOURCE_SYSTEM_GENERATED = 'system_generated';

    public const SOURCE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'schedule_period_id',
        'schedule_period_member_id',
        'security_id',
        'shift_id',
        'schedule_pattern_id',
        'pattern_day_order',
        'date',
        'source',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'pattern_day_order' => 'integer',
        'source' => 'string',
    ];

    public function schedulePeriod(): BelongsTo
    {
        return $this->belongsTo(SchedulePeriod::class, 'schedule_period_id');
    }

    public function schedulePeriodMember(): BelongsTo
    {
        return $this->belongsTo(SchedulePeriodMember::class, 'schedule_period_member_id');
    }

    public function security(): BelongsTo
    {
        return $this->belongsTo(Security::class, 'security_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function schedulePattern(): BelongsTo
    {
        return $this->belongsTo(SchedulePattern::class, 'schedule_pattern_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attendanceRecord(): HasOne
    {
        return $this->hasOne(AttendanceRecord::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $schedule) {
            $periodUnitId = SchedulePeriod::query()
                ->whereKey($schedule->schedule_period_id)
                ->value('unit_id');

            if ($periodUnitId === null) {
                return;
            }

            if ($schedule->shift_id !== null) {
                $shiftUnitId = Shift::query()
                    ->whereKey($schedule->shift_id)
                    ->value('unit_id');

                if ($shiftUnitId !== null && (int) $shiftUnitId !== (int) $periodUnitId) {
                    throw ValidationException::withMessages([
                        'shift_id' => 'Shift harus berada pada unit yang sama dengan periode jadwal.',
                    ]);
                }
            }

            if ($schedule->schedule_pattern_id !== null) {
                $patternUnitId = SchedulePattern::query()
                    ->whereKey($schedule->schedule_pattern_id)
                    ->value('unit_id');

                if ($patternUnitId !== null && (int) $patternUnitId !== (int) $periodUnitId) {
                    throw ValidationException::withMessages([
                        'schedule_pattern_id' => 'Pola rotasi harus berada pada unit yang sama dengan periode jadwal.',
                    ]);
                }
            }

            if ($schedule->schedule_period_member_id !== null) {
                $member = SchedulePeriodMember::query()
                    ->whereKey($schedule->schedule_period_member_id)
                    ->first(['id', 'schedule_period_id', 'security_id']);

                if ($member !== null) {
                    if ((int) $member->schedule_period_id !== (int) $schedule->schedule_period_id) {
                        throw ValidationException::withMessages([
                            'schedule_period_member_id' => 'Anggota periode harus berasal dari periode jadwal yang sama.',
                        ]);
                    }

                    if ((int) $member->security_id !== (int) $schedule->security_id) {
                        throw ValidationException::withMessages([
                            'security_id' => 'Satpam pada jadwal harus sama dengan anggota periode.',
                        ]);
                    }
                }
            }
        });
    }
}
