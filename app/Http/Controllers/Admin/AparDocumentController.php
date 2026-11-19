<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AparInspectionExport;
use App\Http\Controllers\Concerns\AuthorizesAssetAccess;
use App\Http\Controllers\Controller;
use App\Models\Apar;
use App\Services\PatrolPointQrCodeService;
use App\Support\AparAnnualCardData;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AparDocumentController extends Controller
{
    use AuthorizesAssetAccess;

    public function __construct(private readonly PatrolPointQrCodeService $qrCodeService)
    {
    }

    public function qrPdf(Request $request, int $apar): HttpResponse
    {
        $model = $this->findAuthorizedApar($request, $apar);

        $qrDataUri = $this->qrCodeService->dataUri((string) $model->qr_code);
        $printedAt = now()->timezone('Asia/Jakarta')->format('d-m-Y H:i') . ' WIB';

        $pdf = new Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
        ]);
        $pdf->loadHtml(view('admin.apar.qr-pdf', [
            'apar' => $model,
            'aparTypeLabel' => $this->aparTypeLabel($model->apar_type),
            'qrDataUri' => $qrDataUri,
            'printedAt' => $printedAt,
        ])->render(), 'UTF-8');
        $pdf->setPaper('a4', 'portrait');
        $pdf->render();

        $filename = 'qr-apar-' . ($model->apar_code ?: $model->id) . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function annualCard(Request $request, int $apar): HttpResponse
    {
        $model = $this->findAuthorizedApar($request, $apar);
        $year = AparAnnualCardData::resolveYear($request->query('year', now()->year));
        $card = (new AparAnnualCardData)->build($model, $year);

        $printedAt = now()->timezone('Asia/Jakarta')->format('d-m-Y H:i') . ' WIB';

        $pdf = new Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
        ]);
        $pdf->loadHtml(view('admin.apar.annual-card', [
            'apar' => $model,
            'aparTypeLabel' => $this->aparTypeLabel($model->apar_type),
            'year' => $card['year'],
            'months' => $card['months'],
            'inspectionsByMonth' => $card['inspectionsByMonth'],
            'printedAt' => $printedAt,
        ])->render(), 'UTF-8');
        $pdf->setPaper('a4', 'portrait');
        $pdf->render();

        $filename = 'kartu-inspeksi-apar-' . ($model->apar_code ?: $model->id) . '-' . $year . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $scope = $this->assetUnitScope($request);
        $filters = $this->normalizedExportFilters($request, $scope);

        // If apar_id specified ensure it is in scope
        if ($filters['apar_id'] !== null) {
            $this->findAuthorizedApar($request, (int) $filters['apar_id']);
        }

        $filename = $this->exportFilename($filters);

        return Excel::download(new AparInspectionExport($filters), $filename);
    }

    private function findAuthorizedApar(Request $request, int $id): Apar
    {
        $scope = $this->assetUnitScope($request);
        $apar = Apar::query()->with('unit')->findOrFail($id);
        $this->authorizeAssetRecord($scope, $apar);

        return $apar;
    }

    /**
     * @param array<int,int>|null $scope
     * @return array{apar_id:int|null,search:string,result:string,date_from:string,date_to:string,unit_scope:array<int,int>|null}
     */
    private function normalizedExportFilters(Request $request, ?array $scope): array
    {
        $aparId = $request->query('apar_id');
        $aparId = is_numeric($aparId) ? (int) $aparId : null;

        $result = trim((string) $request->query('result', ''));
        if (! in_array($result, ['ok', 'not_ok'], true)) {
            $result = '';
        }

        return [
            'apar_id' => $aparId,
            'search' => trim((string) $request->query('search', '')),
            'result' => $result,
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'unit_scope' => $scope,
        ];
    }

    private function exportFilename(array $filters): string
    {
        $parts = ['riwayat-inspeksi-apar'];
        if (! empty($filters['apar_id'])) {
            $parts[] = 'apar-' . $filters['apar_id'];
        }
        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $from = $filters['date_from'] ?: 'awal';
            $to = $filters['date_to'] ?: 'akhir';
            $parts[] = $from . '-sd-' . $to;
        } else {
            $parts[] = now()->timezone('Asia/Jakarta')->format('Ymd-His');
        }

        return implode('-', $parts) . '.xlsx';
    }

    private function aparTypeLabel(string $type): string
    {
        return match ($type) {
            'dry_chemical' => 'Dry Chemical',
            'co2' => 'CO2',
            'foam' => 'Foam',
            'air' => 'Air',
            'powder' => 'Powder',
            default => $type,
        };
    }
}
