<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait ResolvesAttendanceScope
{
    /**
     * Unit scope for the current user.
     *
     * - null  => unlimited (superadmin)
     * - array => allowed unit ids (user unit + descendants)
     *
     * @return array<int, int>|null
     */
    protected function attendanceScope(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        if ($user->role === 'superadmin') {
            return null;
        }

        if ($user->unit_id === null) {
            return [];
        }

        return array_map('intval', $user->unit?->descendantIds() ?? [(int) $user->unit_id]);
    }

    /**
     * Abort unless the unit is inside the scope.
     *
     * @param  array<int, int>|null  $scope
     */
    protected function assertAttendanceUnit(?array $scope, ?int $unitId): void
    {
        if ($scope !== null && ($unitId === null || ! in_array((int) $unitId, $scope, true))) {
            abort(403);
        }
    }

    protected function attendanceUnitRules(Request $request): array
    {
        $scope = $this->attendanceScope($request);

        return $scope === null
            ? ['integer', 'exists:units,id']
            : ['integer', Rule::in($scope)];
    }

    /**
     * Effective unit filter for list pages (scope-safe).
     *
     * @param  array<int, int>|null  $scope
     */
    protected function attendanceFilterUnitId(Request $request, ?array $scope): ?int
    {
        $raw = trim((string) $request->query('unit_id', ''));

        if ($raw === '' || ! is_numeric($raw)) {
            if ($scope === null) {
                return null;
            }

            $userUnitId = $request->user()?->unit_id;

            return $userUnitId !== null && in_array((int) $userUnitId, $scope, true)
                ? (int) $userUnitId
                : null;
        }

        $candidate = (int) $raw;

        if ($scope !== null && ! in_array($candidate, $scope, true)) {
            return null;
        }

        return $candidate;
    }

    /**
     * @param  array<int, int>|null  $scope
     * @return array<int, array{value: int, label: string}>
     */
    protected function attendanceUnits(?array $scope): array
    {
        return Unit::query()
            ->when($scope !== null, fn ($query) => $query->whereIn('id', $scope))
            ->orderBy('unit_name')
            ->get(['id', 'unit_name'])
            ->map(fn (Unit $unit) => [
                'value' => $unit->id,
                'label' => $unit->unit_name,
            ])
            ->toArray();
    }
}
