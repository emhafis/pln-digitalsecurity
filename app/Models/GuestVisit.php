<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'guest_book_qr_code',
        'full_name',
        'phone_number',
        'company_name',
        'position',
        'visit_purpose',
        'person_to_meet',
        'vehicle_plate_number',
        'check_in_at',
        'check_out_at',
        'checkout_source',
        'checked_out_by',
        'signature_path',
        'selfie_path',
        'consent_given',
        'consent_at',
        'unit_name_snapshot',
        'unit_code_snapshot',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'consent_at' => 'datetime',
            'consent_given' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }
}
