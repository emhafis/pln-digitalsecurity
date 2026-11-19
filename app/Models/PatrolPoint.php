<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PatrolPoint extends Model
{
    protected $fillable = [
        'qr_code',
        'name',
        'security_post_id',
    ];

    protected static function booted(): void
    {
        static::created(function (PatrolPoint $point) {
            if (is_null($point->qr_code)) {
                $point->updateQuietly([
                    'qr_code' => Str::uuid()->toString(),
                ]);
            }
        });
    }

    public function securityPost(): BelongsTo
    {
        return $this->belongsTo(SecurityPost::class, 'security_post_id');
    }
}
