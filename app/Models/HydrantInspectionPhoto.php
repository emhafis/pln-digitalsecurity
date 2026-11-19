<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HydrantInspectionPhoto extends Model
{
    protected $fillable = [
        'hydrant_inspection_id',
        'photo_path',
        'caption',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(HydrantInspection::class, 'hydrant_inspection_id');
    }
}
