<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\GuestBookQrService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuestBookQrController extends Controller
{
    public function __construct(private readonly GuestBookQrService $qrService)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $isSuperadmin = $user?->role === 'superadmin';
        $scope = $this->unitScope($request);

        $query = Unit::query();
        $this->scopeToUnits($query, $scope);

        $units = $query
            ->orderBy('unit_name')
            ->get(['id', 'unit_name', 'unit_code', 'is_active', 'guest_book_qr_code', 'guest_book_checkout_qr_code'])
            ->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'unit_name' => $unit->unit_name,
                'unit_code' => $unit->unit_code,
                'is_active' => (bool) $unit->is_active,
                'checkin_ready' => filled($unit->guest_book_qr_code),
                'checkout_ready' => filled($unit->guest_book_checkout_qr_code),
                'detail_url' => route('admin.guest-book.qr.show', $unit->id),
            ])
            ->values();

        $missingQuery = Unit::query()->whereNull('guest_book_checkout_qr_code');
        $this->scopeToUnits($missingQuery, $scope);
        $missingCheckout = $missingQuery->count();

        return Inertia::render('Admin/GuestBook/Qr/Index', [
            'units' => $units,
            'missingCheckoutCount' => $missingCheckout,
            'canManage' => $isSuperadmin,
        ]);
    }

    public function show(Request $request, Unit $unit): Response
    {
        $this->authorizeUnitAccess($request, $unit);
        $user = $request->user();
        $isSuperadmin = $user?->role === 'superadmin';

        $checkInUrl = $this->qrService->checkInUrl($unit);
        $checkOutUrl = $this->qrService->checkOutUrl($unit);

        return Inertia::render('Admin/GuestBook/Qr/Show', [
            'unit' => [
                'id' => $unit->id,
                'unit_name' => $unit->unit_name,
                'unit_code' => $unit->unit_code,
                'is_active' => (bool) $unit->is_active,
            ],
            'canManage' => $isSuperadmin,
            'checkin' => [
                'uuid' => $unit->guest_book_qr_code,
                'url' => $checkInUrl,
                'ready' => filled($unit->guest_book_qr_code),
                'qr_data_uri' => $checkInUrl ? $this->qrService->pngDataUri($checkInUrl) : null,
                'download_url' => filled($unit->guest_book_qr_code) ? route('admin.guest-book.qr.download', [$unit->id, 'checkin']) : null,
                'regenerate_url' => route('admin.guest-book.qr.regenerate-checkin', $unit->id),
            ],
            'checkout' => [
                'uuid' => $unit->guest_book_checkout_qr_code,
                'url' => $checkOutUrl,
                'ready' => filled($unit->guest_book_checkout_qr_code),
                'qr_data_uri' => $checkOutUrl ? $this->qrService->pngDataUri($checkOutUrl) : null,
                'download_url' => filled($unit->guest_book_checkout_qr_code) ? route('admin.guest-book.qr.download', [$unit->id, 'checkout']) : null,
                'regenerate_url' => route('admin.guest-book.qr.regenerate-checkout', $unit->id),
            ],
        ]);
    }

    public function download(Request $request, Unit $unit, string $type)
    {
        $this->authorizeUnitAccess($request, $unit);

        if ($type === 'checkin') {
            $url = $this->qrService->checkInUrl($unit);
            $label = 'checkin';
        } elseif ($type === 'checkout') {
            $url = $this->qrService->checkOutUrl($unit);
            $label = 'checkout';
        } else {
            abort(404);
        }

        abort_unless($url, 404);

        $png = $this->qrService->pngString($url);
        $code = $unit->unit_code ?: $unit->id;
        $filename = "guest-book-qr-{$label}-{$code}.png";

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function regenerateCheckIn(Unit $unit)
    {
        $this->qrService->regenerateCheckIn($unit);

        return redirect()->route('admin.guest-book.qr.show', $unit->id)
            ->with('success', 'QR Check-in berhasil diregenerasi. QR lama tidak berlaku untuk transaksi baru.');
    }

    public function regenerateCheckOut(Unit $unit)
    {
        $this->qrService->regenerateCheckOut($unit);

        return redirect()->route('admin.guest-book.qr.show', $unit->id)
            ->with('success', 'QR Check-out berhasil diregenerasi. QR lama tidak berlaku untuk transaksi baru.');
    }

    public function backfill()
    {
        $count = $this->qrService->backfillMissingCheckoutQrCodes();

        if ($count === 0) {
            return redirect()->route('admin.guest-book.qr.index')
                ->with('success', 'Tidak ada unit dengan QR Check-out kosong. Tidak ada perubahan data.');
        }

        return redirect()->route('admin.guest-book.qr.index')
            ->with('success', "Backfill selesai: {$count} unit diperbarui (hanya QR Check-out yang NULL).");
    }

    private function authorizeUnitAccess(Request $request, Unit $unit): void
    {
        $scope = $this->unitScope($request);
        if ($scope === null) {
            return;
        }
        abort_unless(in_array((int) $unit->id, $scope, true), 403);
    }

    private function scopeToUnits(Builder $query, ?array $scope): Builder
    {
        if ($scope === null) {
            return $query;
        }

        return $query->whereIn('id', $scope);
    }

    private function unitScope(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return [];
        }
        if ($user->role === 'superadmin') {
            return null;
        }
        if ($user->role !== 'admin' || $user->unit_id === null) {
            return [];
        }

        return array_values(array_map('intval', $user->unit?->descendantIds() ?? [(int) $user->unit_id]));
    }
}
