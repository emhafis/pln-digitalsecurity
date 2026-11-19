<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatrolScanPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'patrol_scan_id',
        'photo_path',
        'sequence',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(PatrolScan::class, 'patrol_scan_id');
    }
}
