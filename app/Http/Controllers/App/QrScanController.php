<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\AuthorizesAssetAccess;
use App\Http\Controllers\Controller;
use App\Models\Apar;
use App\Models\Hydrant;
use App\Models\Security;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrScanController extends Controller
{
    use AuthorizesAssetAccess;

    public function index(Request $request)
    {
        $user = $request->user();
        if ($user === null || $user->role !== 'security') {
            abort(403);
        }

        return \Inertia\Inertia::render('App/Scan/Index');
    }

    public function resolve(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null || $user->role !== 'security' || ! $user->is_active || $user->unit_id === null) {
            abort(403);
        }
        if (! Security::query()->where('user_id', $user->id)->exists()) {
            abort(403);
        }

        $validated = $request->validate([
            'qr_code' => ['required', 'uuid'],
        ]);

        $scope = $this->assetUnitScope($request);
        $qrCode = $validated['qr_code'];

        $apar = Apar::query()->where('qr_code', $qrCode)->first();
        if ($apar !== null) {
            $this->authorizeAssetRecord($scope, $apar);
            if (! $apar->is_active) {
                return response()->json(['message' => 'Aset tidak aktif.'], 422);
            }
            return response()->json(['type' => 'apar', 'url' => '/app/apar/' . $apar->id]);
        }

        $hydrant = Hydrant::query()->where('qr_code', $qrCode)->first();
        if ($hydrant !== null) {
            $this->authorizeAssetRecord($scope, $hydrant);
            if (! $hydrant->is_active) {
                return response()->json(['message' => 'Aset tidak aktif.'], 422);
            }
            return response()->json(['type' => 'hydrant', 'url' => '/app/hydrant/' . $hydrant->id]);
        }

        return response()->json(['message' => 'QR Code tidak dikenali.'], 404);
    }
}
