<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AparInspectionPhoto extends Model
{
    protected $fillable = [
        'apar_inspection_id',
        'photo_path',
        'caption',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(AparInspection::class, 'apar_inspection_id');
    }
}
