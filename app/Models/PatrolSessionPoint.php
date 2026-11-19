<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatrolSessionPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'patrol_session_id',
        'patrol_point_id',
        'patrol_point_name',
        'patrol_point_qr_code',
        'sequence',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function patrolSession(): BelongsTo
    {
        return $this->belongsTo(PatrolSession::class, 'patrol_session_id');
    }

    public function patrolPoint(): BelongsTo
    {
        return $this->belongsTo(PatrolPoint::class, 'patrol_point_id');
    }
}
