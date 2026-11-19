<?php

namespace App\Services;

use App\Models\Unit;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestBookQrService
{
    public function checkInUrl(Unit $unit): ?string
    {
        $uuid = $unit->guest_book_qr_code;
        return $uuid ? url('/guest-book/'.$uuid) : null;
    }

    public function checkOutUrl(Unit $unit): ?string
    {
        $uuid = $unit->guest_book_checkout_qr_code;
        return $uuid ? url('/guest-book/checkout/'.$uuid) : null;
    }

    public function pngDataUri(string $payload): string
    {
        $result = (new Builder(
            writer: new PngWriter(),
            data: $payload,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 240,
            margin: 8,
        ))->build();

        return $result->getDataUri();
    }

    public function pngString(string $payload): string
    {
        $result = (new Builder(
            writer: new PngWriter(),
            data: $payload,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 360,
            margin: 10,
        ))->build();

        return $result->getString();
    }

    public function backfillMissingCheckoutQrCodes(): int
    {
        return DB::transaction(function () {
            $updated = 0;
            Unit::query()
                ->whereNull('guest_book_checkout_qr_code')
                ->lockForUpdate()
                ->get()
                ->each(function (Unit $unit) use (&$updated) {
                    $unit->guest_book_checkout_qr_code = $this->uniqueCheckoutUuid();
                    $unit->save();
                    $updated++;
                });

            return $updated;
        });
    }

    public function regenerateCheckIn(Unit $unit): string
    {
        return DB::transaction(function () use ($unit) {
            $locked = Unit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $locked->guest_book_qr_code = $this->uniqueCheckInUuid($locked->id);
            $locked->save();

            return $locked->guest_book_qr_code;
        });
    }

    public function regenerateCheckOut(Unit $unit): string
    {
        return DB::transaction(function () use ($unit) {
            $locked = Unit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $locked->guest_book_checkout_qr_code = $this->uniqueCheckoutUuid($locked->id);
            $locked->save();

            return $locked->guest_book_checkout_qr_code;
        });
    }

    private function uniqueCheckInUuid(?int $ignoreId = null): string
    {
        do {
            $uuid = (string) Str::uuid();
        } while (Unit::query()->where('guest_book_qr_code', $uuid)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists());

        return $uuid;
    }

    private function uniqueCheckoutUuid(?int $ignoreId = null): string
    {
        do {
            $uuid = (string) Str::uuid();
        } while (Unit::query()->where('guest_book_checkout_qr_code', $uuid)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists());

        return $uuid;
    }
}
