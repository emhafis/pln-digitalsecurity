<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatrolSessionMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'patrol_session_id',
        'schedule_id',
        'security_id',
        'security_name',
        'security_registration_number',
    ];

    public function patrolSession(): BelongsTo
    {
        return $this->belongsTo(PatrolSession::class, 'patrol_session_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function security(): BelongsTo
    {
        return $this->belongsTo(Security::class, 'security_id');
    }
}
