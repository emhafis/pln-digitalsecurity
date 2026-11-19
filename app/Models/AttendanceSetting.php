<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'gps_radius_meters',
        'check_in_open_minutes',
        'late_tolerance_minutes',
        'early_checkout_tolerance_minutes',
        'checkout_close_minutes',
        'max_gps_accuracy_meters',
        'updated_by',
    ];

    protected $casts = [
        'gps_radius_meters' => 'integer',
        'check_in_open_minutes' => 'integer',
        'late_tolerance_minutes' => 'integer',
        'early_checkout_tolerance_minutes' => 'integer',
        'checkout_close_minutes' => 'integer',
        'max_gps_accuracy_meters' => 'integer',
    ];

    /**
     * Default values defined by the business rules.
     *
     * @return array<string, int>
     */
    public static function defaults(): array
    {
        return [
            'gps_radius_meters' => 50,
            'check_in_open_minutes' => 15,
            'late_tolerance_minutes' => 5,
            'early_checkout_tolerance_minutes' => 0,
            'checkout_close_minutes' => 60,
            'max_gps_accuracy_meters' => 50,
        ];
    }

    /**
     * The single active configuration row.
     */
    public static function current(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Validation issues for the global settings, in Bahasa Indonesia.
     *
     * @return array<int, array{field: string, message: string}>
     */
    public function validationIssues(): array
    {
        $issues = [];

        foreach (self::defaults() as $field => $default) {
            $value = $this->getAttribute($field);

            if ($value === null || ! is_numeric($value) || (int) $value < 0) {
                $issues[] = [
                    'field' => $field,
                    'message' => 'Nilai pengaturan '.$field.' belum diisi atau tidak valid.',
                ];
            }
        }

        if ((int) $this->gps_radius_meters <= 0) {
            $issues[] = [
                'field' => 'gps_radius_meters',
                'message' => 'Radius lokasi harus lebih besar dari 0 meter.',
            ];
        }

        if ((int) $this->max_gps_accuracy_meters <= 0) {
            $issues[] = [
                'field' => 'max_gps_accuracy_meters',
                'message' => 'Akurasi lokasi maksimal harus lebih besar dari 0 meter.',
            ];
        }

        return $issues;
    }

    public function isValid(): bool
    {
        return $this->validationIssues() === [];
    }
}
