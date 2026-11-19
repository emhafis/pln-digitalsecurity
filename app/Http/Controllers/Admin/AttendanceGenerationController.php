<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesAttendanceScope;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\AttendanceGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceGenerationController extends Controller
{
    use ResolvesAttendanceScope;

    public function store(Request $request, AttendanceGenerator $generator): RedirectResponse
    {
        $validated = $request->validate([
            'period_id' => ['nullable', 'integer', 'exists:schedule_periods,id'],
            'unit_id' => ['nullable', ...$this->attendanceUnitRules($request)],
        ]);

        $scope = $this->attendanceScope($request);
        $unitId = $validated['unit_id'] ?? null;
        $unitIds = $scope;

        if ($unitId !== null) {
            $unitIds = array_map('intval', Unit::query()->find($unitId)?->descendantIds() ?? [$unitId]);
        }

        $result = $generator->generateAll(
            $request->user(),
            $unitIds,
            $validated['period_id'] ?? null
        );

        if ($result['blocked_by_global']) {
            return back()->with('error', 'Pembuatan data absensi diblokir karena Pengaturan Absensi belum valid.');
        }

        if ($result['errors'] !== []) {
            return back()
                ->with('error', 'Sebagian periode belum siap sehingga data absensi belum dibuat.')
                ->with('generation_result', $result);
        }

        return back()
            ->with('success', sprintf(
                'Pembuatan data absensi selesai: %d dibuat, %d disinkronkan, %d terkunci, %d data bermasalah.',
                $result['created'],
                $result['synced'],
                $result['locked'],
                $result['anomalies']
            ))
            ->with('generation_result', $result);
    }
}
