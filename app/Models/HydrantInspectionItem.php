<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HydrantInspectionItem extends Model
{
    protected $fillable = [
        'hydrant_inspection_id',
        'item_name',
        'item_label',
        'is_good',
        'note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_good' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(HydrantInspection::class, 'hydrant_inspection_id');
    }
}
