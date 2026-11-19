<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hydrant extends Model
{
    protected $fillable = [
        'unit_id',
        'hydrant_code',
        'name',
        'hydrant_type',
        'brand',
        'model',
        'serial_number',
        'procurement_date',
        'installation_date',
        'last_maintenance_date',
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
            'last_maintenance_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(HydrantInspection::class);
    }
}
