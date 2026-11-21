<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuestVisit;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuestBookController extends Controller
{
    public function index(Request $request): Response
    {
        $scope = $this->unitScope($request);
        if ($scope !== null && $scope === []) {
            // admin without unit access
        }

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $activeOnly = (bool) $request->boolean('active_only');

        $base = GuestVisit::query();
        $this->scopeToUnits($base, $scope);

        $today = now()->toDateString();

        $activeCount = (clone $base)->whereNull('check_out_at')->count();
        $todayCount = (clone $base)->whereDate('check_in_at', $today)->count();
        $checkedOutTodayCount = (clone $base)->whereDate('check_out_at', $today)->count();

        $list = GuestVisit::query()->with(['unit:id,unit_name,unit_code']);
        $this->scopeToUnits($list, $scope);
        $this->applyFilters($list, $search, $status, $dateFrom, $dateTo, $activeOnly);

        $paginator = $list
            ->orderByRaw('check_out_at is null desc')
            ->orderByDesc('check_in_at')
            ->paginate(20)
            ->withQueryString();

        $activeVisits = (clone $base)
            ->with(['unit:id,unit_name,unit_code'])
            ->whereNull('check_out_at')
            ->orderByDesc('check_in_at')
            ->limit(10)
            ->get();

        return Inertia::render('Admin/GuestBook/Index', [
            'stats' => [
                'active' => $activeCount,
                'today' => $todayCount,
                'checked_out_today' => $checkedOutTodayCount,
            ],
            'activeVisits' => $activeVisits->map(fn (GuestVisit $v) => $this->listItem($v)),
            'items' => $paginator->getCollection()->map(fn (GuestVisit $v) => $this->listItem($v)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => [
                'search' => $search,
                'status' => in_array($status, ['active', 'checked_out'], true) ? $status : '',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'active_only' => $activeOnly ? '1' : '',
            ],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $visit = $this->findScopedVisit($request, $id);

        return Inertia::render('Admin/GuestBook/Show', [
            'visit' => $this->detailItem($visit),
        ]);
    }

    public function checkout(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $scope = $this->unitScope($request);
        $user = $request->user();

        $result = DB::transaction(function () use ($id, $scope, $user) {
            $visit = GuestVisit::query()->whereKey($id)->lockForUpdate()->first();
            if (! $visit) {
                abort(404);
            }
            if (! $this->isVisitInScope($visit, $scope)) {
                abort(403);
            }
            if ($visit->check_out_at !== null) {
                return 'already';
            }

            $visit->forceFill([
                'check_out_at' => now(),
                'checkout_source' => 'admin',
                'checked_out_by' => $user?->id,
            ])->save();

            return 'ok';
        });

        if ($result === 'already') {
            return back()->with('warning', 'Kunjungan sudah tercatat selesai.');
        }

        return back()->with('success', 'Check-out manual berhasil dicatat.');
    }

    public function file(Request $request, int $id, string $type): StreamedResponse|HttpResponse
    {
        $visit = $this->findScopedVisit($request, $id);

        if (! in_array($type, ['selfie', 'signature'], true)) {
            abort(404);
        }

        $path = $type === 'selfie' ? $visit->selfie_path : $visit->signature_path;
        if (! is_string($path) || $path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            abort(404);
        }
        if (! str_starts_with($path, 'guest-book/')) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path);
    }

    private function applyFilters(Builder $query, string $search, string $status, string $dateFrom, string $dateTo, bool $activeOnly): void
    {
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('person_to_meet', 'like', "%{$search}%")
                    ->orWhere('visit_purpose', 'like', "%{$search}%");
            });
        }

        if ($activeOnly || $status === 'active') {
            $query->whereNull('check_out_at');
        } elseif ($status === 'checked_out') {
            $query->whereNotNull('check_out_at');
        }

        if ($dateFrom !== '') {
            $query->whereDate('check_in_at', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->whereDate('check_in_at', '<=', $dateTo);
        }
    }

    private function listItem(GuestVisit $v): array
    {
        $active = $v->check_out_at === null;
        return [
            'id' => $v->id,
            'full_name' => $v->full_name,
            'phone_number' => $this->maskedPhone($v->phone_number),
            'company_name' => $v->company_name,
            'visit_purpose' => $v->visit_purpose,
            'person_to_meet' => $v->person_to_meet,
            'unit_name' => $v->unit_name_snapshot ?: ($v->unit?->unit_name ?? '-'),
            'unit_code' => $v->unit_code_snapshot ?: ($v->unit?->unit_code ?? null),
            'check_in_at' => $v->check_in_at?->format('d M Y, H:i'),
            'check_out_at' => $v->check_out_at?->format('d M Y, H:i'),
            'status' => $active ? 'active' : 'checked_out',
            'status_label' => $active ? 'Masih di Lokasi' : 'Sudah Pulang',
            'detail_url' => route('admin.guest-book.show', $v->id),
        ];
    }

    private function detailItem(GuestVisit $v): array
    {
        $v->loadMissing(['unit:id,unit_name,unit_code', 'checkedOutBy:id,name']);
        $active = $v->check_out_at === null;
        return [
            'id' => $v->id,
            'full_name' => $v->full_name,
            'phone_number' => $this->maskedPhone($v->phone_number),
            'company_name' => $v->company_name,
            'position' => $v->position,
            'visit_purpose' => $v->visit_purpose,
            'person_to_meet' => $v->person_to_meet,
            'vehicle_plate_number' => $v->vehicle_plate_number,
            'check_in_at' => $v->check_in_at?->format('d M Y, H:i'),
            'check_out_at' => $v->check_out_at?->format('d M Y, H:i'),
            'checkout_source' => $v->checkout_source,
            'checkout_source_label' => $v->checkout_source === 'admin' ? 'Admin' : ($v->checkout_source === 'guest' ? 'Tamu' : null),
            'checked_out_by_name' => $v->checkedOutBy?->name,
            'status' => $active ? 'active' : 'checked_out',
            'status_label' => $active ? 'Masih di Lokasi' : 'Sudah Pulang',
            'unit_name' => $v->unit_name_snapshot ?: ($v->unit?->unit_name ?? '-'),
            'unit_code' => $v->unit_code_snapshot ?: ($v->unit?->unit_code ?? null),
            'has_selfie' => ! empty($v->selfie_path),
            'has_signature' => ! empty($v->signature_path),
            'selfie_url' => ! empty($v->selfie_path) ? '/storage/' . $v->selfie_path : null,
            'signature_url' => ! empty($v->signature_path) ? '/storage/' . $v->signature_path : null,
            'consent_given' => (bool) $v->consent_given,
            'consent_at' => $v->consent_at?->format('d M Y, H:i'),
            'can_checkout' => $active,
        ];
    }

    private function maskedPhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') return '-';
        $len = strlen($digits);
        if ($len <= 4) return $digits;
        return str_repeat('*', max(0, $len - 4)).substr($digits, -4);
    }

    private function findScopedVisit(Request $request, int $id): GuestVisit
    {
        $scope = $this->unitScope($request);
        $visit = GuestVisit::query()->whereKey($id)->first();
        if (! $visit) {
            abort(404);
        }
        if (! $this->isVisitInScope($visit, $scope)) {
            abort(403);
        }
        return $visit;
    }

    private function isVisitInScope(GuestVisit $visit, ?array $scope): bool
    {
        if ($scope === null) return true;
        return in_array((int) $visit->unit_id, $scope, true);
    }

    private function scopeToUnits(Builder $query, ?array $scope): Builder
    {
        if ($scope === null) {
            return $query;
        }
        return $query->whereIn('unit_id', $scope);
    }

    private function unitScope(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) return [];
        if ($user->role === 'superadmin') return null;
        if ($user->role !== 'admin' || $user->unit_id === null) return [];

        return array_values(array_map('intval', $user->unit?->descendantIds() ?? [(int) $user->unit_id]));
    }
}
