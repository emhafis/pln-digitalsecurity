<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AparInspection extends Model
{
    protected $fillable = [
        'apar_id',
        'unit_id',
        'security_id',
        'inspected_at',
        'result',
        'notes',
        'unit_name_snapshot',
        'unit_code_snapshot',
        'apar_code_snapshot',
        'apar_type_snapshot',
        'brand_snapshot',
        'capacity_snapshot',
        'serial_number_snapshot',
        'placement_snapshot',
        'apar_expiry_date_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'inspected_at' => 'datetime',
            'apar_expiry_date_snapshot' => 'date',
        ];
    }

    public function apar(): BelongsTo
    {
        return $this->belongsTo(Apar::class);
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
        return $this->hasMany(AparInspectionItem::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AparInspectionPhoto::class);
    }
}
