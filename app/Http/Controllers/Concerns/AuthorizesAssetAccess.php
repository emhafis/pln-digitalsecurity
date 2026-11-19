<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Security;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait AuthorizesAssetAccess
{
    /**
     * Resolve allowed unit IDs for asset access.
     *
     * - null  => unlimited (superadmin)
     * - array => allowed unit IDs
     *
     * @return array<int,int>|null
     */
    protected function assetUnitScope(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        if ($user->role === 'superadmin') {
            return null;
        }

        if ($user->role === 'admin') {
            if ($user->unit_id === null) {
                return [];
            }

            return array_values(array_map('intval', $user->unit?->descendantIds() ?? [(int) $user->unit_id]));
        }

        if ($user->role === 'security') {
            // Security: active account, linked profile, and own unit only.
            if (! $user->is_active || $user->unit_id === null) {
                return [];
            }

            $hasProfile = Security::query()->where('user_id', $user->id)->exists();
            if (! $hasProfile) {
                return [];
            }

            return [(int) $user->unit_id];
        }

        return [];
    }

    /**
     * Abort unless the given unit is inside the scope.
     *
     * @param  array<int,int>|null  $scope
     */
    protected function authorizeAssetUnit(?array $scope, ?int $unitId): void
    {
        if ($scope !== null && ($unitId === null || ! in_array((int) $unitId, $scope, true))) {
            abort(403);
        }
    }

    /**
     * Scope an asset query (models with unit_id) to allowed units.
     *
     * @param  array<int,int>|null  $scope
     */
    protected function scopeAssetsToUnit(Builder $query, ?array $scope): Builder
    {
        if ($scope === null) {
            return $query;
        }

        return $query->whereIn('unit_id', $scope);
    }

    /**
     * Authorize access to an asset model that belongs to a unit.
     *
     * @param  array<int,int>|null  $scope
     */
    protected function authorizeAssetRecord(?array $scope, Model $asset): void
    {
        $unitId = $asset->getAttribute('unit_id');
        $this->authorizeAssetUnit($scope, $unitId !== null ? (int) $unitId : null);
    }

    /**
     * Resolve the linked Security profile for the authenticated security user.
     */
    protected function authenticatedSecurity(Request $request): ?Security
    {
        $user = $request->user();
        if ($user === null || $user->role !== 'security') {
            return null;
        }

        return Security::query()->where('user_id', $user->id)->first();
    }
}
