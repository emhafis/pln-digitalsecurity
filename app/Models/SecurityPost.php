<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecurityPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_code',
        'name',
        'address',
        'latitude',
        'longitude',
        'unit_id',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected static function booted()
    {
        static::created(function ($securityPost) {
            $securityPost->updateQuietly([
                'post_code' => 'SP'.str_pad($securityPost->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function securityTeams(): HasMany
    {
        return $this->hasMany(SecurityTeam::class);
    }

    public function schedulePeriods(): HasMany
    {
        return $this->hasMany(SchedulePeriod::class);
    }

    public function patrolPoints(): HasMany
    {
        return $this->hasMany(PatrolPoint::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
