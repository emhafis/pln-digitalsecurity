<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Throwable;

class ActivityLogService
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        '_token',
        'remember_token',
        'session_id',
        'secret',
        'api_key',
        'apikey',
        'authorization',
        'cookie',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function log(array $attributes): ?ActivityLog
    {
        try {
            $user = Auth::user();
            $subject = $attributes['subject'] ?? null;
            $subjectType = $attributes['subject_type'] ?? null;
            $subjectId = $attributes['subject_id'] ?? null;

            if ($subject instanceof Model) {
                $subjectType ??= $subject->getMorphClass();
                $subjectId ??= $subject->getKey();
            }

            $oldValues = $this->sanitizeValues($attributes['old_values'] ?? null);
            $newValues = $this->sanitizeValues($attributes['new_values'] ?? null);

            return ActivityLog::query()->create([
                'user_id' => $user?->id,
                'user_name' => $attributes['user_name'] ?? $user?->name ?? $user?->username ?? 'System',
                'action' => (string) ($attributes['action'] ?? 'unknown'),
                'module' => (string) ($attributes['module'] ?? 'System'),
                'description' => (string) ($attributes['description'] ?? '-'),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'unit_id' => $this->resolveUnitId($attributes['unit_id'] ?? null, $subject),
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => Request::ip(),
                'user_agent' => $this->truncate(Request::userAgent(), 1000),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Logging must not break business flow.
            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    public function sanitizeValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $sanitized = [];
        foreach ($values as $key => $value) {
            $keyString = (string) $key;
            $lower = strtolower($keyString);
            $isSensitive = false;
            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if ($lower === $sensitive || str_contains($lower, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }
            if ($isSensitive) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$keyString] = $this->sanitizeValues($value);
            } elseif ($value instanceof Model) {
                $sanitized[$keyString] = $value->getKey();
            } elseif (is_scalar($value) || $value === null) {
                $sanitized[$keyString] = $value;
            } else {
                $sanitized[$keyString] = (string) $value;
            }
        }

        return $sanitized === [] ? null : $sanitized;
    }

    private function resolveUnitId(mixed $unitId, mixed $subject): ?int
    {
        if (is_numeric($unitId)) {
            return (int) $unitId;
        }

        if ($subject instanceof Model && isset($subject->unit_id) && is_numeric($subject->unit_id)) {
            return (int) $subject->unit_id;
        }

        return null;
    }

    private function truncate(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }
}
