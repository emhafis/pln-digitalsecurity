<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityTeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'security_team_id',
        'security_id',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'joined_at' => 'date',
        'left_at' => 'date',
    ];

    /**
     * Keep the active marker consistent with left_at on every model save.
     *
     * active_security_id is a nullable unique guard: it is filled only while the
     * membership is active and NULL once the membership ends. The database unique
     * index then guarantees a security can only have one active membership.
     */
    protected static function booted(): void
    {
        static::saving(function (self $member): void {
            $member->active_security_id = $member->left_at === null
                ? $member->security_id
                : null;
        });
    }

    public function securityTeam(): BelongsTo
    {
        return $this->belongsTo(SecurityTeam::class, 'security_team_id');
    }

    public function security(): BelongsTo
    {
        return $this->belongsTo(Security::class, 'security_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('left_at');
    }

    public function isActive(): bool
    {
        return $this->left_at === null;
    }
}
