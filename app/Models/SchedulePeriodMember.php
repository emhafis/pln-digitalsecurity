<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class SchedulePeriodMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_period_id',
        'security_id',
        'schedule_pattern_id',
        'start_offset',
    ];

    protected $casts = [
        'start_offset' => 'integer',
    ];

    public function schedulePeriod(): BelongsTo
    {
        return $this->belongsTo(SchedulePeriod::class, 'schedule_period_id');
    }

    public function security(): BelongsTo
    {
        return $this->belongsTo(Security::class, 'security_id');
    }

    public function schedulePattern(): BelongsTo
    {
        return $this->belongsTo(SchedulePattern::class, 'schedule_pattern_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $member) {
            if ($member->schedule_pattern_id === null) {
                return;
            }

            $periodUnitId = SchedulePeriod::query()
                ->whereKey($member->schedule_period_id)
                ->value('unit_id');
            $pattern = SchedulePattern::query()
                ->whereKey($member->schedule_pattern_id)
                ->first(['id', 'unit_id', 'is_active']);

            if ($periodUnitId === null || $pattern === null) {
                return;
            }

            if ((int) $periodUnitId !== (int) $pattern->unit_id) {
                throw ValidationException::withMessages([
                    'schedule_pattern_id' => 'Pola rotasi harus berada pada unit yang sama dengan periode.',
                ]);
            }

            if ($member->isDirty('schedule_pattern_id') && ! $pattern->is_active) {
                throw ValidationException::withMessages([
                    'schedule_pattern_id' => 'Pola rotasi nonaktif tidak dapat digunakan untuk periode jadwal baru.',
                ]);
            }
        });
    }
}
