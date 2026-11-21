<?php

namespace App\Services;

use App\Models\PatrolSession;
use App\Support\AttendanceClock;
use Illuminate\Support\Carbon;

/**
 * Resolves the effective display status of a patrol session without changing
 * the stored session status. Only pending sessions are evaluated against the
 * snapshot shift window; terminal and running statuses keep their meaning.
 */
class PatrolSessionStatusService
{
    public const DISPLAY_WAITING = 'waiting';

    public const DISPLAY_NOT_STARTED = 'not_started';

    public const DISPLAY_ACTIVE = 'active';

    public const DISPLAY_COMPLETED = 'completed';

    public const DISPLAY_INCOMPLETE = 'incomplete';

    public const DISPLAY_MISSED = 'missed';

    public function __construct(
        private readonly PatrolSessionOperationService $operation
    ) {}

    /**
     * @return array{status:string,label:string,variant:string}
     */
    public function resolve(PatrolSession $session, ?Carbon $now = null): array
    {
        $now ??= AttendanceClock::nowUtc();

        if ($session->status === PatrolSession::STATUS_COMPLETED) {
            return $this->result(self::DISPLAY_COMPLETED, 'Selesai', 'emerald');
        }

        if ($session->status === PatrolSession::STATUS_INCOMPLETE) {
            return $this->result(self::DISPLAY_INCOMPLETE, 'Tidak Lengkap', 'red');
        }

        if ($session->status === PatrolSession::STATUS_ACTIVE) {
            return $this->result(self::DISPLAY_ACTIVE, 'Berlangsung', 'blue');
        }

        if ($session->status === PatrolSession::STATUS_NOT_STARTED) {
            return $this->result(self::DISPLAY_MISSED, 'Tidak Dimulai', 'red');
        }

        if ($session->status !== PatrolSession::STATUS_PENDING) {
            return $this->result(self::DISPLAY_MISSED, 'Tidak Dimulai', 'red');
        }

        // Historical inconsistency guard: a pending row that already records a
        // start must not be reported as not started.
        if ($session->started_at !== null) {
            return $this->result(self::DISPLAY_ACTIVE, 'Berlangsung', 'blue');
        }

        $window = $this->operation->startWindowInfo($session, $now);

        return match ($window['state'] ?? 'invalid') {
            'before' => $this->result(self::DISPLAY_WAITING, 'Menunggu', 'amber'),
            'open' => $this->result(self::DISPLAY_NOT_STARTED, 'Belum Dimulai', 'gray'),
            default => $this->result(self::DISPLAY_MISSED, 'Tidak Dimulai', 'red'),
        };
    }

    /**
     * @return array{status:string,label:string,variant:string}
     */
    private function result(string $status, string $label, string $variant): array
    {
        return [
            'status' => $status,
            'label' => $label,
            'variant' => $variant,
        ];
    }
}
