<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Apar extends Model
{
    protected $fillable = [
        'unit_id',
        'apar_code',
        'apar_type',
        'brand',
        'capacity',
        'serial_number',
        'procurement_date',
        'installation_date',
        'recharge_date',
        'expiry_date',
        'placement',
        'qr_code',
        'photo',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'procurement_date' => 'date',
            'installation_date' => 'date',
            'recharge_date' => 'date',
            'expiry_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(AparInspection::class);
    }
    public function typeName(): string
    {
        return match ($this->apar_type) {
            'dry_chemical' => 'Dry Chemical',
            'co2' => 'CO2',
            'foam' => 'Foam',
            'air' => 'Air',
            'powder' => 'Powder',
            default => (string) $this->apar_type,
        };
    }
}
