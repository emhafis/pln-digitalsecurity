<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatrolScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'patrol_session_id',
        'patrol_point_id',
        'security_id',
        'patrol_point_name',
        'patrol_point_qr_code',
        'security_name',
        'security_registration_number',
        'scanned_at',
        'latitude',
        'longitude',
        'accuracy',
        'has_incident',
        'incident_description',
        'incident_photo_path',
        'idempotency_key',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'accuracy' => 'decimal:2',
        'has_incident' => 'boolean',
    ];

    public function patrolSession(): BelongsTo
    {
        return $this->belongsTo(PatrolSession::class, 'patrol_session_id');
    }

    public function patrolPoint(): BelongsTo
    {
        return $this->belongsTo(PatrolPoint::class, 'patrol_point_id');
    }

    public function security(): BelongsTo
    {
        return $this->belongsTo(Security::class, 'security_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PatrolScanPhoto::class, 'patrol_scan_id')->orderBy('sequence');
    }
}
