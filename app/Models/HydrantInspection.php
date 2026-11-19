<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HydrantInspection extends Model
{
    protected $fillable = [
        'hydrant_id',
        'unit_id',
        'security_id',
        'inspected_at',
        'result',
        'notes',
        'unit_name_snapshot',
        'unit_code_snapshot',
        'hydrant_code_snapshot',
        'name_snapshot',
        'hydrant_type_snapshot',
        'brand_snapshot',
        'model_snapshot',
        'serial_number_snapshot',
        'placement_snapshot',
        'last_maintenance_date_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'inspected_at' => 'datetime',
            'last_maintenance_date_snapshot' => 'date',
        ];
    }

    public function hydrant(): BelongsTo
    {
        return $this->belongsTo(Hydrant::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function security(): BelongsTo
    {
        return $this->belongsTo(Security::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(HydrantInspectionItem::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(HydrantInspectionPhoto::class);
    }
}
