<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\GuestVisit;
use App\Models\Unit;
use App\Support\GuestBookPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;

class GuestCheckOutController extends Controller
{
    private const NOT_FOUND_MESSAGE = 'Data kunjungan tidak ditemukan atau sudah selesai.';

    public function show(Request $request, string $uuid)
    {
        $unit = $this->resolveCheckoutUnit($uuid);
        if (! $unit) {
            return $this->invalidQrResponse($request);
        }

        return Inertia::render('GuestBook/CheckOut', [
            'qrCode' => $unit->guest_book_checkout_qr_code,
            'unit' => [
                'name' => $unit->unit_name,
                'code' => $unit->unit_code,
            ],
        ]);
    }

    public function lookup(Request $request, string $uuid)
    {
        $unit = $this->resolveCheckoutUnit($uuid);
        if (! $unit) {
            return $this->invalidQrResponse($request, 422);
        }

        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:30'],
            'unit_id' => ['prohibited'],
            'guest_visit_id' => ['prohibited'],
        ]);

        $normalized = GuestBookPhone::normalize($validated['phone_number']);
        $matches = $this->findActiveMatches($unit->id, $normalized);

        if ($matches->isEmpty()) {
            $this->registerFailedLookup($request);
            return back()->withErrors(['phone_number' => self::NOT_FOUND_MESSAGE])->withInput();
        }

        $this->clearFailedLookup($request);

        $candidates = $matches->map(fn (GuestVisit $v) => [
            'id' => $v->id,
            'full_name' => $v->full_name,
            'company_name' => $v->company_name,
            'check_in_at' => $v->check_in_at?->format('d M Y, H:i'),
        ])->values()->all();

        return back()->with([
            'candidates' => $candidates,
            'phone_number' => trim($validated['phone_number']),
            'single_candidate' => count($candidates) === 1 ? $candidates[0] : null,
        ]);
    }

    public function confirm(Request $request, string $uuid)
    {
        $unit = $this->resolveCheckoutUnit($uuid);
        if (! $unit) {
            return $this->invalidQrResponse($request, 422);
        }

        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:30'],
            'guest_visit_id' => ['required', 'integer'],
            'unit_id' => ['prohibited'],
            'check_out_at' => ['prohibited'],
            'checkout_source' => ['prohibited'],
            'checked_out_by' => ['prohibited'],
        ]);

        $normalized = GuestBookPhone::normalize($validated['phone_number']);
        $visitId = (int) $validated['guest_visit_id'];

        $status = DB::transaction(function () use ($unit, $visitId, $normalized) {
            $visit = GuestVisit::query()
                ->whereKey($visitId)
                ->lockForUpdate()
                ->first();

            if (! $visit || (int) $visit->unit_id !== (int) $unit->id) {
                return 'not_found';
            }

            if (GuestBookPhone::normalize($visit->phone_number) !== $normalized) {
                return 'not_found';
            }

            if ($visit->check_out_at !== null) {
                return 'already';
            }

            $visit->forceFill([
                'check_out_at' => now(),
                'checkout_source' => 'guest',
                'checked_out_by' => null,
            ])->save();

            return 'checked_out';
        });

        if ($status === 'not_found') {
            $this->registerFailedLookup($request);
            return back()->withErrors(['phone_number' => self::NOT_FOUND_MESSAGE]);
        }

        $this->clearFailedLookup($request);

        return redirect()->route('guest-book.checkout.success');
    }

    public function success()
    {
        return Inertia::render('GuestBook/CheckOutSuccess');
    }

    private function findActiveMatches(int $unitId, string $normalizedPhone)
    {
        if ($normalizedPhone === '') {
            return collect();
        }

        return GuestVisit::query()
            ->where('unit_id', $unitId)
            ->whereNull('check_out_at')
            ->orderByDesc('check_in_at')
            ->get()
            ->filter(fn (GuestVisit $v) => GuestBookPhone::normalize($v->phone_number) === $normalizedPhone)
            ->values();
    }

    private function resolveCheckoutUnit(string $uuid): ?Unit
    {
        if (! Str::isUuid($uuid)) {
            return null;
        }

        return Unit::query()
            ->whereNotNull('guest_book_checkout_qr_code')
            ->where('guest_book_checkout_qr_code', $uuid)
            ->first();
    }

    private function invalidQrResponse(Request $request, int $status = 404)
    {
        if ($request->header('X-Inertia')) {
            return Inertia::render('GuestBook/Invalid', [
                'message' => 'QR Code tidak valid atau tidak dapat digunakan.',
            ])->toResponse($request)->setStatusCode($status);
        }

        return response()->view('guest-book.invalid', [
            'message' => 'QR Code tidak valid atau tidak dapat digunakan.',
        ], $status);
    }

    private function failureKey(Request $request): string
    {
        return 'guest-checkout-failures:'.($request->ip() ?: 'guest');
    }

    private function registerFailedLookup(Request $request): void
    {
        $key = $this->failureKey($request);
        $attempts = (int) RateLimiter::attempts($key);
        $threshold = max(1, (int) env('GUEST_BOOK_CHECKOUT_FAIL_THRESHOLD', 5));
        $cooldown = max(1, (int) env('GUEST_BOOK_CHECKOUT_FAIL_COOLDOWN_MINUTES', 15));
        $decay = max(1, (int) env('GUEST_BOOK_CHECKOUT_FAIL_DECAY_MINUTES', 5));

        $decaySeconds = ($attempts + 1 >= $threshold) ? $cooldown * 60 : $decay * 60;
        RateLimiter::hit($key, $decaySeconds);

        if (RateLimiter::attempts($key) >= $threshold) {
            \Illuminate\Support\Facades\Log::warning('guestbook.checkout.failures.cooldown', [
                'ip_hash' => hash('sha256', (string) $request->ip()),
            ]);
            abort(429, 'Terlalu banyak percobaan. Silakan coba lagi nanti.');
        }
    }

    private function clearFailedLookup(Request $request): void
    {
        RateLimiter::clear($this->failureKey($request));
    }
}
