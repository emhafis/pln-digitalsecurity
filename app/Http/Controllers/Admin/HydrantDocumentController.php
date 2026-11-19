<?php

namespace App\Http\Controllers\Admin;

use App\Exports\HydrantInspectionExport;
use App\Http\Controllers\Concerns\AuthorizesAssetAccess;
use App\Http\Controllers\Controller;
use App\Models\Hydrant;
use App\Services\PatrolPointQrCodeService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HydrantDocumentController extends Controller
{
    use AuthorizesAssetAccess;

    public function __construct(private readonly PatrolPointQrCodeService $qrCodeService)
    {
    }

    public function qrPdf(Request $request, int $hydrant): HttpResponse
    {
        $model = $this->findAuthorizedHydrant($request, $hydrant);

        $qrDataUri = $this->qrCodeService->dataUri((string) $model->qr_code);
        $printedAt = now()->timezone('Asia/Jakarta')->format('d-m-Y H:i').' WIB';

        $pdf = new Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
        ]);
        $pdf->loadHtml(view('admin.hydrant.qr-pdf', [
            'hydrant' => $model,
            'hydrantTypeLabel' => $this->hydrantTypeLabel($model->hydrant_type),
            'qrDataUri' => $qrDataUri,
            'printedAt' => $printedAt,
        ])->render(), 'UTF-8');
        $pdf->setPaper('a4', 'portrait');
        $pdf->render();

        $filename = 'qr-hydrant-'.($model->hydrant_code ?: $model->id).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $scope = $this->assetUnitScope($request);
        $filters = $this->normalizedExportFilters($request, $scope);

        if ($filters['hydrant_id'] !== null) {
            $this->findAuthorizedHydrant($request, (int) $filters['hydrant_id']);
        }

        $filename = $this->exportFilename($filters);

        return Excel::download(new HydrantInspectionExport($filters), $filename);
    }

    private function findAuthorizedHydrant(Request $request, int $id): Hydrant
    {
        $scope = $this->assetUnitScope($request);
        $hydrant = Hydrant::query()->with('unit')->findOrFail($id);
        $this->authorizeAssetRecord($scope, $hydrant);

        return $hydrant;
    }

    /**
     * @param array<int,int>|null $scope
     * @return array{hydrant_id:int|null,search:string,result:string,date_from:string,date_to:string,unit_scope:array<int,int>|null}
     */
    private function normalizedExportFilters(Request $request, ?array $scope): array
    {
        $hydrantId = $request->query('hydrant_id');
        $hydrantId = is_numeric($hydrantId) ? (int) $hydrantId : null;

        $result = trim((string) $request->query('result', ''));
        if (! in_array($result, ['ok', 'not_ok'], true)) {
            $result = '';
        }

        return [
            'hydrant_id' => $hydrantId,
            'search' => trim((string) $request->query('search', '')),
            'result' => $result,
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'unit_scope' => $scope,
        ];
    }

    private function exportFilename(array $filters): string
    {
        $parts = ['riwayat-inspeksi-hydrant'];
        if (! empty($filters['hydrant_id'])) {
            $parts[] = 'hydrant-'.$filters['hydrant_id'];
        }
        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $from = $filters['date_from'] ?: 'awal';
            $to = $filters['date_to'] ?: 'akhir';
            $parts[] = $from.'-sd-'.$to;
        } else {
            $parts[] = now()->timezone('Asia/Jakarta')->format('Ymd-His');
        }

        return implode('-', $parts).'.xlsx';
    }

    private function hydrantTypeLabel(string $type): string
    {
        return match ($type) {
            'fire_pump_diesel' => 'Fire Pump Diesel',
            'fire_pump_electric' => 'Fire Pump Electric',
            'jockey_pump' => 'Jockey Pump',
            'hydrant_box' => 'Hydrant Box',
            'hydrant_pillar' => 'Hydrant Pillar',
            'siamese_connection' => 'Siamese Connection',
            default => $type,
        };
    }
}
