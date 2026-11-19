<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesAttendanceScope;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Services\AttendanceCorrectionService;
use App\Support\AttendanceClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceCorrectionController extends Controller
{
    use ResolvesAttendanceScope;

    public function index(Request $request): Response
    {
        $scope = $this->attendanceScope($request);
        $search = trim((string) $request->query('search', ''));

        $status = (string) $request->query('status', AttendanceCorrection::STATUS_PENDING);
        if (! array_key_exists($status, AttendanceCorrection::STATUS_LABELS)) {
            $status = AttendanceCorrection::STATUS_PENDING;
        }

        $unitId = $this->attendanceFilterUnitId($request, $scope);

        $query = AttendanceCorrection::query()
            ->with([
                'attendanceRecord:id,security_name,security_registration_number,unit_name,security_post_name,work_date,shift_name,status,corrected_status',
                'requester:id,name',
                'reviewer:id,name',
            ])
            ->when($scope !== null, function ($query) use ($scope) {
                $query->whereHas('attendanceRecord', fn ($record) => $record->whereIn('unit_id', $scope));
            })
            ->when($unitId !== null, function ($query) use ($unitId) {
                $query->whereHas('attendanceRecord', fn ($record) => $record->where('unit_id', $unitId));
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('attendanceRecord', function ($record) use ($search) {
                    $record->where('security_name', 'like', "%{$search}%")
                        ->orWhere('security_registration_number', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id');

        $paginator = $query->paginate(50)->withQueryString();

        return Inertia::render('Admin/Attendance/Corrections/Index', [
            'corrections' => $paginator->getCollection()->map(fn (AttendanceCorrection $correction) => [
                'id' => $correction->id,
                'attendance_record_id' => $correction->attendance_record_id,
                'security_name' => $correction->attendanceRecord?->security_name ?? '-',
                'registration_number' => $correction->attendanceRecord?->security_registration_number,
                'unit_name' => $correction->attendanceRecord?->unit_name ?? '-',
                'post_name' => $correction->attendanceRecord?->security_post_name ?? '-',
                'work_date_label' => $correction->attendanceRecord?->work_date?->format('d M Y'),
                'shift_name' => $correction->attendanceRecord?->shift_name,
                'current_status_label' => $correction->attendanceRecord !== null
                    ? $correction->attendanceRecord->effectiveStatusLabel()
                    : '-',
                'status' => $correction->status,
                'status_label' => $correction->statusLabel(),
                'reason' => $correction->reason,
                'requested_by' => $correction->requester?->name,
                'requested_by_role' => $correction->requested_by_role,
                'requested_check_in' => AttendanceClock::formatWib($correction->requested_check_in_at),
                'requested_check_out' => AttendanceClock::formatWib($correction->requested_check_out_at),
                'requested_status' => $correction->requested_status !== null
                    ? AttendanceRecord::statusLabel($correction->requested_status)
                    : null,
                'requested_at' => AttendanceClock::formatWib($correction->created_at),
                'reviewed_by' => $correction->reviewer?->name,
                'reviewed_at' => AttendanceClock::formatWib($correction->reviewed_at),
                'review_note' => $correction->review_note,
                'show_url' => route('admin.attendance-record.show', $correction->attendance_record_id),
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'statuses' => collect(AttendanceCorrection::STATUS_LABELS)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'units' => $this->attendanceUnits($scope),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'unit_id' => $unitId ?? '',
            ],
        ]);
    }

    public function approve(
        Request $request,
        int $id,
        AttendanceCorrectionService $service
    ): RedirectResponse {
        $correction = $this->findScoped($request, $id);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->approve($correction, $request->user(), $validated['review_note'] ?? null);

        return back()->with('success', 'Pengajuan koreksi disetujui.');
    }

    public function reject(
        Request $request,
        int $id,
        AttendanceCorrectionService $service
    ): RedirectResponse {
        $correction = $this->findScoped($request, $id);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->reject($correction, $request->user(), $validated['review_note'] ?? null);

        return back()->with('success', 'Pengajuan koreksi ditolak.');
    }

    private function findScoped(Request $request, int $id): AttendanceCorrection
    {
        $correction = AttendanceCorrection::query()
            ->with('attendanceRecord')
            ->findOrFail($id);

        $this->assertAttendanceUnit(
            $this->attendanceScope($request),
            (int) $correction->attendanceRecord?->unit_id
        );

        return $correction;
    }
}
