<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SchedulePeriod extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'unit_id',
        'security_post_id',
        'security_team_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => 'string',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function securityPost(): BelongsTo
    {
        return $this->belongsTo(SecurityPost::class, 'security_post_id');
    }

    public function securityTeam(): BelongsTo
    {
        return $this->belongsTo(SecurityTeam::class, 'security_team_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(SchedulePeriodMember::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Inclusive date range overlap query.
     */
    public function scopeOverlapping(
        Builder $query,
        Carbon|string $startDate,
        Carbon|string $endDate
    ): Builder {
        $start = Carbon::parse($startDate)->toDateString();
        $end = Carbon::parse($endDate)->toDateString();

        return $query
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start);
    }

    public function hasOverlap(): bool
    {
        return static::query()
            ->where('security_team_id', $this->security_team_id)
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->overlapping($this->start_date, $this->end_date)
            ->exists();
    }

    /**
     * Copy the previous period's member snapshot into this period.
     *
     * This only copies snapshot rows; it never reads current team membership.
     */
    public function copyMembersFrom(self $source): int
    {
        if ($source->is($this)) {
            throw ValidationException::withMessages([
                'schedule_period_id' => 'Periode sumber harus berbeda dari periode tujuan.',
            ]);
        }

        if ((int) $source->security_team_id !== (int) $this->security_team_id) {
            throw ValidationException::withMessages([
                'schedule_period_id' => 'Snapshot hanya dapat disalin dari periode regu yang sama.',
            ]);
        }

        return DB::transaction(function () use ($source) {
            $copied = 0;

            $source->members()->each(function (SchedulePeriodMember $member) use (&$copied) {
                $snapshot = $this->members()->firstOrCreate(
                    ['security_id' => $member->security_id],
                    [
                        'schedule_pattern_id' => $member->schedule_pattern_id,
                        'start_offset' => $member->start_offset,
                    ]
                );

                if ($snapshot->wasRecentlyCreated) {
                    $copied++;
                }
            });

            return $copied;
        });
    }

    /**
     * Add a security snapshot row after validating unit and team scope.
     */
    public function addMember(
        Security $security,
        ?SchedulePattern $pattern = null,
        int $startOffset = 0
    ): SchedulePeriodMember {
        if (! $this->securityTeam->acceptsSecurity($security)) {
            throw ValidationException::withMessages([
                'security_id' => 'Unit satpam tidak sesuai dengan unit pos jaga regu.',
            ]);
        }

        if ($pattern !== null && (int) $pattern->unit_id !== (int) $this->unit_id) {
            throw ValidationException::withMessages([
                'schedule_pattern_id' => 'Pola rotasi harus berada pada unit yang sama dengan periode.',
            ]);
        }

        return $this->members()->updateOrCreate(
            ['security_id' => $security->id],
            [
                'schedule_pattern_id' => $pattern?->id,
                'start_offset' => $startOffset,
            ]
        );
    }

    protected static function booted(): void
    {
        static::saving(function (self $period) {
            if ($period->start_date === null || $period->end_date === null) {
                return;
            }

            if ($period->start_date->gt($period->end_date)) {
                throw ValidationException::withMessages([
                    'end_date' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
                ]);
            }

            $postUnitId = SecurityPost::query()
                ->whereKey($period->security_post_id)
                ->value('unit_id');

            if ($postUnitId === null || (int) $postUnitId !== (int) $period->unit_id) {
                throw ValidationException::withMessages([
                    'security_post_id' => 'Post jaga harus berada pada unit yang sama dengan periode.',
                ]);
            }

            $teamPostId = SecurityTeam::query()
                ->whereKey($period->security_team_id)
                ->value('security_post_id');

            if ($teamPostId === null || (int) $teamPostId !== (int) $period->security_post_id) {
                throw ValidationException::withMessages([
                    'security_team_id' => 'Regu harus berada pada pos jaga yang sama dengan periode.',
                ]);
            }

            $teamPostUnitId = SecurityTeam::query()
                ->join(
                    'security_posts',
                    'security_teams.security_post_id',
                    '=',
                    'security_posts.id'
                )
                ->where('security_teams.id', $period->security_team_id)
                ->value('security_posts.unit_id');

            if ($teamPostUnitId === null || (int) $teamPostUnitId !== (int) $period->unit_id) {
                throw ValidationException::withMessages([
                    'security_team_id' => 'Regu harus berada pada unit yang sama dengan periode.',
                ]);
            }

            if ($period->hasOverlap()) {
                throw ValidationException::withMessages([
                    'start_date' => 'Periode jadwal untuk regu ini sudah ada pada rentang tanggal tersebut.',
                ]);
            }
        });
    }
}
