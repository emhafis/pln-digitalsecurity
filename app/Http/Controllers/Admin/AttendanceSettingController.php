<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSetting;
use App\Support\AttendanceClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceSettingController extends Controller
{
    public function edit(Request $request): Response
    {
        $settings = AttendanceSetting::current();

        return Inertia::render('Admin/AttendanceSetting/Edit', [
            'settings' => $this->transform($settings),
            'defaults' => AttendanceSetting::defaults(),
            'updater' => $settings?->updater?->name,
            'updated_at' => AttendanceClock::formatWib($settings?->updated_at),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gps_radius_meters' => ['required', 'integer', 'min:1', 'max:100000'],
            'check_in_open_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'late_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'early_checkout_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'checkout_close_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'max_gps_accuracy_meters' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $settings = AttendanceSetting::current();

            if ($settings === null) {
                AttendanceSetting::create([
                    ...$validated,
                    'updated_by' => $request->user()->id,
                ]);

                return;
            }

            $settings->update([
                ...$validated,
                'updated_by' => $request->user()->id,
            ]);
        });

        return redirect()
            ->route('admin.attendance-setting.edit')
            ->with('success', 'Pengaturan Absensi berhasil disimpan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(?AttendanceSetting $settings): array
    {
        $defaults = AttendanceSetting::defaults();
        $values = [];

        foreach ($defaults as $field => $default) {
            $values[$field] = $settings?->getAttribute($field) ?? $default;
        }

        return [
            ...$values,
            'is_saved' => $settings !== null,
            'is_valid' => $settings?->isValid() ?? false,
            'validation_issues' => $settings?->validationIssues() ?? [],
        ];
    }
}
