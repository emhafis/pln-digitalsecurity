<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    public const TYPE_WORKING = 'working';

    public const TYPE_DAY_OFF = 'day_off';

    public const NAME_PAGI = 'Pagi';

    public const NAME_SIANG = 'Siang';

    public const NAME_MALAM = 'Malam';

    public const NAME_STAND_BY = 'Stand By';

    public const NAME_LEPAS_DINAS = 'Lepas Dinas';

    public const NAMES = [
        self::NAME_PAGI,
        self::NAME_SIANG,
        self::NAME_MALAM,
        self::NAME_STAND_BY,
        self::NAME_LEPAS_DINAS,
    ];

    public const CODE_BY_NAME = [
        self::NAME_PAGI => 'P',
        self::NAME_SIANG => 'S',
        self::NAME_MALAM => 'M',
        self::NAME_STAND_BY => 'SB',
        self::NAME_LEPAS_DINAS => 'L',
    ];

    public const TYPE_BY_NAME = [
        self::NAME_PAGI => self::TYPE_WORKING,
        self::NAME_SIANG => self::TYPE_WORKING,
        self::NAME_MALAM => self::TYPE_WORKING,
        self::NAME_STAND_BY => self::TYPE_WORKING,
        self::NAME_LEPAS_DINAS => self::TYPE_DAY_OFF,
    ];

    protected $fillable = [
        'unit_id',
        'name',
        'code',
        'type',
        'start_time',
        'end_time',
        'end_day_offset',
        'color',
        'is_active',
    ];

    protected $casts = [
        'end_day_offset' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'type' => self::TYPE_WORKING,
        'end_day_offset' => 0,
    ];

    public function isDayOff(): bool
    {
        return $this->type === self::TYPE_DAY_OFF;
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function patternDetails(): HasMany
    {
        return $this->hasMany(SchedulePatternDetail::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
