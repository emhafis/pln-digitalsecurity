<?php

namespace App\Http\Controllers\App;

use App\Exports\AparInspectionExport;
use App\Http\Controllers\Concerns\AuthorizesAssetAccess;
use App\Http\Controllers\Controller;
use App\Models\Apar;
use App\Models\Security;
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
        $this->authorizeSecurityAccess($request);
        $model = $this->findOwnedApar($request, $apar);

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
        $this->authorizeSecurityAccess($request);
        $model = $this->findOwnedApar($request, $apar);
        $year = AparAnnualCardData::resolveYear($request->query('year', now()->year));
        $card = (new AparAnnualCardData)->build($model, $year, (int) $request->user()->unit_id);

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


    private function findOwnedApar(Request $request, int $id): Apar
    {
        $scope = $this->assetUnitScope($request);
        $apar = Apar::query()
            ->whereKey($id)
            ->when($scope !== null, fn ($q) => $q->whereIn('unit_id', $scope))
            ->with('unit')
            ->first();

        if ($apar === null) {
            abort(404);
        }

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

    private function authorizeSecurityAccess(Request $request): void
    {
        $user = $request->user();
        if ($user === null || $user->role !== 'security' || ! $user->is_active || $user->unit_id === null) {
            abort(403);
        }

        $hasProfile = Security::query()->where('user_id', $user->id)->exists();
        if (! $hasProfile) {
            abort(403);
        }
    }
}
