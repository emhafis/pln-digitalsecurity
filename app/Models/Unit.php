<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'unit_code',
        'unit_name',
        'unit_level',
        'is_active',
        'guest_book_qr_code',
        'guest_book_checkout_qr_code',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Unit $unit): void {
            if (blank($unit->guest_book_qr_code)) {
                $unit->guest_book_qr_code = static::generateUniqueQrCode('guest_book_qr_code');
            }
            if (blank($unit->guest_book_checkout_qr_code)) {
                $unit->guest_book_checkout_qr_code = static::generateUniqueQrCode('guest_book_checkout_qr_code', $unit->guest_book_qr_code);
            }
        });

        static::created(function (Unit $unit): void {
            $unit->updateQuietly([
                'unit_code' => 'U'.str_pad($unit->id, 4, '0', STR_PAD_LEFT),
            ]);
        });
    }

    protected static function generateUniqueQrCode(string $column, ?string $notEqualTo = null): string
    {
        do {
            $uuid = (string) Str::uuid();
        } while (
            ($notEqualTo !== null && hash_equals($notEqualTo, $uuid))
            || static::query()->where($column, $uuid)->exists()
        );

        return $uuid;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Unit::class, 'parent_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function schedulePatterns(): HasMany
    {
        return $this->hasMany(SchedulePattern::class);
    }

    public function schedulePeriods(): HasMany
    {
        return $this->hasMany(SchedulePeriod::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function guestVisits(): HasMany
    {
        return $this->hasMany(GuestVisit::class);
    }

    public function descendantIds(): array
    {
        $ids = [$this->id];

        $parentIds = [$this->id];

        while (! empty($parentIds)) {
            $childIds = self::whereIn(
                'parent_id',
                $parentIds
            )->pluck('id')->all();

            $childIds = array_values(
                array_diff($childIds, $ids)
            );

            if (empty($childIds)) {
                break;
            }

            $ids = array_merge($ids, $childIds);

            $parentIds = $childIds;
        }

        return $ids;
    }
}
