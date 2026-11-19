<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Security extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'registration_number',
        'full_name',
        'phone_number',
        'address',
        'birth_place',
        'birth_date',
        'kta_number',
        'kta_file',
        'kta_issued_date',
        'kta_expired_date',
        'photo',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'kta_issued_date' => 'date',
        'kta_expired_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::created(function (Security $security) {
            $security->updateQuietly([
                'registration_number' => 'SEC'.str_pad($security->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function securityTeamMembers(): HasMany
    {
        return $this->hasMany(SecurityTeamMember::class);
    }

    public function activeSecurityTeamMembership(): HasOne
    {
        return $this->hasOne(SecurityTeamMember::class)->whereNull('left_at');
    }

    public function schedulePeriodMembers(): HasMany
    {
        return $this->hasMany(SchedulePeriodMember::class);
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
