<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class SchedulePattern extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'name',
        'description',
        'cycle_length',
        'is_active',
    ];

    protected $casts = [
        'cycle_length' => 'integer',
        'is_active' => 'boolean',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(SchedulePatternDetail::class)->orderBy('day_order');
    }

    public function schedulePeriodMembers(): HasMany
    {
        return $this->hasMany(SchedulePeriodMember::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Add one rotation day to this pattern after validating the shift's unit.
     */
    public function addDetail(Shift $shift, int $dayOrder): SchedulePatternDetail
    {
        if ((int) $shift->unit_id !== (int) $this->unit_id) {
            throw ValidationException::withMessages([
                'shift_id' => 'Shift harus berada pada unit yang sama dengan pola rotasi.',
            ]);
        }

        return $this->details()->create([
            'shift_id' => $shift->id,
            'day_order' => $dayOrder,
        ]);
    }
}
