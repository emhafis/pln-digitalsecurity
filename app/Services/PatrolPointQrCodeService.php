<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

class PatrolPointQrCodeService
{
    /**
     * Build a QR code data URI for the given payload.
     */
    public function dataUri(string $payload): string
    {
        $result = (new Builder(
            writer: new PngWriter,
            data: $payload,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 240,
            margin: 0,
        ))->build();

        return $result->getDataUri();
    }
}
