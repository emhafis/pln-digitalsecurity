<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class SchedulePatternDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_pattern_id',
        'day_order',
        'shift_id',
    ];

    protected $casts = [
        'day_order' => 'integer',
    ];

    public function schedulePattern(): BelongsTo
    {
        return $this->belongsTo(SchedulePattern::class, 'schedule_pattern_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $detail) {
            if ($detail->schedule_pattern_id === null || $detail->shift_id === null) {
                return;
            }

            $patternUnit = SchedulePattern::query()
                ->whereKey($detail->schedule_pattern_id)
                ->value('unit_id');
            $shiftUnit = Shift::query()
                ->whereKey($detail->shift_id)
                ->value('unit_id');

            if ($patternUnit === null || $shiftUnit === null) {
                return;
            }

            if ((int) $patternUnit !== (int) $shiftUnit) {
                throw ValidationException::withMessages([
                    'shift_id' => 'Shift harus berada pada unit yang sama dengan pola rotasi.',
                ]);
            }
        });
    }
}
