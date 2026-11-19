<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SecurityTeam extends Model
{
    use HasFactory;

    /**
     * Fixed team slots per security post. The key is the stored code and the
     * value is the stored name. Codes are always derived from the name.
     */
    public const NAMES = [
        'A' => 'Regu A',
        'B' => 'Regu B',
        'C' => 'Regu C',
        'D' => 'Regu D',
        'E' => 'Regu E',
        'F' => 'Regu F',
    ];

    /**
     * Resolve the deterministic code for a team name.
     */
    public static function codeForName(?string $name): ?string
    {
        $code = array_search(trim((string) $name), self::NAMES, true);

        return $code === false ? null : $code;
    }

    /**
     * Resolve the canonical name for a team code.
     */
    public static function nameForCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return self::NAMES[$code] ?? null;
    }

    protected $fillable = [
        'security_post_id',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function securityPost(): BelongsTo
    {
        return $this->belongsTo(SecurityPost::class, 'security_post_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(SecurityTeamMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->whereNull('left_at');
    }

    public function schedulePeriods(): HasMany
    {
        return $this->hasMany(SchedulePeriod::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * Determine whether the security personnel's unit is inside the post's unit scope.
     */
    public function acceptsSecurity(Security $security): bool
    {
        $securityUnitId = $security->user?->unit_id;
        $post = $this->securityPost;
        $postUnit = $post?->unit;

        if ($securityUnitId === null || $postUnit === null) {
            return false;
        }

        return in_array((int) $securityUnitId, $postUnit->descendantIds(), true);
    }

    /**
     * Assign a security member to this team, closing any previous active membership.
     *
     * Dates are inclusive: joined_at is the first active day, left_at is the last active day.
     */
    public function assignMember(
        Security $security,
        Carbon|string $joinedAt,
        Carbon|string|null $leftAt = null
    ): SecurityTeamMember {
        $joined = Carbon::parse($joinedAt)->startOfDay();
        $left = $leftAt !== null ? Carbon::parse($leftAt)->startOfDay() : null;

        if ($left !== null && $left->lt($joined)) {
            throw ValidationException::withMessages([
                'left_at' => 'Tanggal keluar tidak boleh lebih awal dari tanggal bergabung.',
            ]);
        }

        if (! $this->acceptsSecurity($security)) {
            throw ValidationException::withMessages([
                'security_id' => 'Unit satpam tidak sesuai dengan unit pos jaga regu.',
            ]);
        }

        try {
            return DB::transaction(function () use ($security, $joined, $left) {
                // Serialize concurrent assignments for the same security by locking
                // the parent row first. This is the consistent lock anchor used by
                // both assignment and membership termination.
                Security::query()
                    ->whereKey($security->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $activeMemberships = SecurityTeamMember::query()
                    ->where('security_id', $security->id)
                    ->whereNull('left_at')
                    ->lockForUpdate()
                    ->get();

                foreach ($activeMemberships as $membership) {
                    if ($membership->joined_at->gte($joined)) {
                        throw ValidationException::withMessages([
                            'security_id' => 'Satpam masih memiliki keanggotaan regu aktif pada tanggal tersebut.',
                        ]);
                    }

                    $membership->update([
                        'left_at' => $joined->copy()->subDay()->toDateString(),
                    ]);
                }

                return $this->members()->create([
                    'security_id' => $security->id,
                    'joined_at' => $joined->toDateString(),
                    'left_at' => $left?->toDateString(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'security_id' => 'Keanggotaan satpam ini baru saja diubah oleh proses lain. Muat ulang data lalu coba lagi.',
            ]);
        }
    }
}
