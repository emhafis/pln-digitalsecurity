<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\GuestVisit;
use App\Models\Unit;
use App\Support\GuestBookFileStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GuestBookController extends Controller
{
    public function show(Request $request, string $uuid)
    {
        $unit = $this->resolveCheckinUnit($uuid);
        if (! $unit) {
            return $this->invalidQrResponse($request);
        }

        return Inertia::render('GuestBook/CheckIn', [
            'qrCode' => $unit->guest_book_qr_code,
            'unit' => [
                'name' => $unit->unit_name,
                'code' => $unit->unit_code,
            ],
        ]);
    }

    public function store(Request $request, string $uuid)
    {
        $unit = $this->resolveCheckinUnit($uuid);
        if (! $unit) {
            return $this->invalidQrResponse($request, 422);
        }

        $selfieFile = $request->file('selfie');
        if ($selfieFile && ! $selfieFile->isValid()) {
            throw ValidationException::withMessages([
                'selfie' => 'Foto selfie gagal diunggah. Pastikan ukuran maksimal 10 MB dan format gambar valid, lalu coba lagi.',
            ]);
        }

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20'],
            'company_name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'visit_purpose' => ['required', 'string', 'max:500'],
            'person_to_meet' => ['required', 'string', 'max:255'],
            'vehicle_plate_number' => ['nullable', 'string', 'max:20'],
            'consent_given' => ['accepted'],
            'selfie' => ['nullable', 'file', 'max:10240', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value instanceof \Illuminate\Http\UploadedFile) {
                    return;
                }
                $mime = strtolower((string) ($value->getMimeType() ?: ''));
                $ext = strtolower((string) $value->getClientOriginalExtension());
                $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg', 'heic', 'heif', 'avif', 'tif', 'tiff'];
                if (! str_starts_with($mime, 'image/') && ! in_array($ext, $allowedExt, true)) {
                    $fail('Foto selfie harus berupa gambar.');
                }
            }],
            'signature' => ['nullable', 'string', 'max:500000'],
            'unit_id' => ['prohibited'],
            'check_in_at' => ['prohibited'],
        ], [
            'consent_given.accepted' => 'Persetujuan penggunaan data wajib diberikan.',
            'selfie.file' => 'Foto selfie harus berupa gambar.',
            'selfie.mimetypes' => 'Foto selfie harus berupa gambar.',
            'selfie.max' => 'Ukuran foto selfie maksimal 10 MB.',
            'selfie.uploaded' => 'Foto selfie gagal diunggah. Pastikan ukuran maksimal 10 MB dan coba lagi.',
        ]);

        $storedPaths = [];
        try {
            DB::transaction(function () use ($request, $unit, $validated, &$storedPaths): void {
                $freshUnit = Unit::query()->whereKey($unit->id)->lockForUpdate()->first();
                if (! $freshUnit || ! $freshUnit->is_active || $freshUnit->guest_book_qr_code !== $unit->guest_book_qr_code) {
                    throw ValidationException::withMessages(['qr' => 'QR Code tidak valid atau unit tidak aktif.']);
                }

                $selfiePath = null;
                if ($request->hasFile('selfie')) {
                    $selfiePath = GuestBookFileStorage::storeUploadedFile($request->file('selfie'), 'selfie', (int) $freshUnit->id);
                    $storedPaths[] = $selfiePath;
                }

                $signaturePath = null;
                $signatureInput = $validated['signature'] ?? null;
                if (is_string($signatureInput) && trim($signatureInput) !== '') {
                    $signaturePath = GuestBookFileStorage::storeDataUrl($signatureInput, 'signature', (int) $freshUnit->id);
                    if ($signaturePath) {
                        $storedPaths[] = $signaturePath;
                    }
                }

                GuestVisit::query()->create([
                    'unit_id' => $freshUnit->id,
                    'guest_book_qr_code' => $freshUnit->guest_book_qr_code,
                    'full_name' => trim($validated['full_name']),
                    'phone_number' => trim($validated['phone_number']),
                    'company_name' => trim($validated['company_name']),
                    'position' => trim($validated['position']),
                    'visit_purpose' => trim($validated['visit_purpose']),
                    'person_to_meet' => trim($validated['person_to_meet']),
                    'vehicle_plate_number' => isset($validated['vehicle_plate_number']) && trim((string) $validated['vehicle_plate_number']) !== '' ? trim((string) $validated['vehicle_plate_number']) : null,
                    'check_in_at' => now(),
                    'consent_given' => true,
                    'consent_at' => now(),
                    'unit_name_snapshot' => $freshUnit->unit_name,
                    'unit_code_snapshot' => $freshUnit->unit_code,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'selfie_path' => $selfiePath,
                    'signature_path' => $signaturePath,
                ]);
            });
        } catch (ValidationException $e) {
            foreach ($storedPaths as $path) {
                GuestBookFileStorage::delete($path);
            }
            if ($e->errors() && isset($e->errors()['qr'])) {
                return $this->invalidQrResponse($request, 422);
            }
            throw $e;
        } catch (\Throwable $e) {
            foreach ($storedPaths as $path) {
                GuestBookFileStorage::delete($path);
            }
            throw $e;
        }

        return redirect()->route('guest-book.success');
    }

    public function success()
    {
        return Inertia::render('GuestBook/Success');
    }

    private function resolveCheckinUnit(string $uuid): ?Unit
    {
        if (! Str::isUuid($uuid)) {
            return null;
        }

        return Unit::query()
            ->where('guest_book_qr_code', $uuid)
            ->where('is_active', true)
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
}
