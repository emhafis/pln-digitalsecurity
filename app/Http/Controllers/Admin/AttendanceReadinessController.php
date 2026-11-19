<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesAttendanceScope;
use App\Http\Controllers\Controller;
use App\Models\AttendanceGenerationAnomaly;
use App\Models\AttendanceSetting;
use App\Models\Unit;
use App\Services\AttendanceReadinessChecker;
use App\Support\AttendanceClock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceReadinessController extends Controller
{
    use ResolvesAttendanceScope;

    public function index(Request $request, AttendanceReadinessChecker $checker): Response
    {
        $scope = $this->attendanceScope($request);
        $unitId = $this->attendanceFilterUnitId($request, $scope);

        $unitIds = $unitId !== null
            ? array_map('intval', Unit::query()->find($unitId)?->descendantIds() ?? [$unitId])
            : $scope;

        $periodId = $request->query->has('period_id') && is_numeric($request->query('period_id'))
            ? (int) $request->query('period_id')
            : null;

        $report = $checker->check($unitIds, $periodId);

        $anomalies = AttendanceGenerationAnomaly::query()
            ->active()
            ->with([
                'schedulePeriod:id,name',
                'security:id,full_name',
            ])
            ->when($unitIds !== null, function ($query) use ($unitIds) {
                $query->whereHas('schedulePeriod', fn ($period) => $period->whereIn('unit_id', $unitIds));
            })
            ->when($periodId !== null, fn ($query) => $query->where('schedule_period_id', $periodId))
            ->orderByDesc('detected_at')
            ->limit(200)
            ->get()
            ->map(fn (AttendanceGenerationAnomaly $anomaly) => [
                'id' => $anomaly->id,
                'type' => $anomaly->anomaly_type,
                'type_label' => $anomaly->typeLabel(),
                'period_name' => $anomaly->schedulePeriod?->name ?? '-',
                'security_name' => $anomaly->security?->full_name ?? '-',
                'work_date' => $anomaly->work_date?->toDateString(),
                'work_date_label' => $anomaly->work_date?->format('d M Y'),
                'details' => $anomaly->details,
                'detected_at' => AttendanceClock::formatWib($anomaly->detected_at),
            ]);

        return Inertia::render('Admin/Attendance/Readiness', [
            'report' => [
                ...$report,
                'periods' => collect($report['periods'])->map(fn (array $period) => [
                    ...$period,
                    'start_date_label' => $period['start_date'] !== null
                        ? Carbon::parse($period['start_date'])->format('d M Y')
                        : null,
                    'end_date_label' => $period['end_date'] !== null
                        ? Carbon::parse($period['end_date'])->format('d M Y')
                        : null,
                ])->values(),
                'global' => [
                    ...$report['global'],
                    'settings' => $report['global']['settings'] !== null
                        ? collect($report['global']['settings']->only(array_keys(AttendanceSetting::defaults())))
                            ->mapWithKeys(fn ($value, $key) => [$key => $value])
                            ->all()
                        : null,
                ],
            ],
            'anomalies' => $anomalies,
            'units' => $this->attendanceUnits($scope),
            'unit_id' => $unitId ?? '',
            'period_id' => $periodId ?? '',
        ]);
    }
}
