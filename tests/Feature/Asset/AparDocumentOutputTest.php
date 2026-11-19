<?php

namespace Tests\Feature\Asset;

use App\Exports\AparInspectionExport;
use App\Models\Apar;
use App\Models\AparInspection;
use App\Models\AparInspectionItem;
use App\Models\Security;
use App\Models\Unit;
use App\Services\PatrolPointQrCodeService;
use App\Support\AparAnnualCardData;
use App\Support\AssetInspectionRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Mockery\MockInterface;
use Tests\Feature\Scheduling\SchedulingTestCase;

class AparDocumentOutputTest extends SchedulingTestCase
{
    private function createApar(Unit $unit, array $attributes = []): Apar
    {
        return Apar::query()->create(array_merge([
            'unit_id' => $unit->id,
            'apar_code' => 'APR-' . strtoupper(Str::random(6)),
            'apar_type' => 'co2',
            'brand' => 'BrandX',
            'capacity' => '6 Kg',
            'serial_number' => 'SN-001',
            'placement' => 'Lobby',
            'expiry_date' => '2027-01-01',
            'qr_code' => (string) Str::uuid(),
            'is_active' => true,
        ], $attributes));
    }

    private function createInspection(Apar $apar, Security $security, array $attributes = []): AparInspection
    {
        $unit = $apar->unit ?? Unit::query()->find($apar->unit_id);
        $inspection = AparInspection::query()->create([
            'apar_id' => $apar->id,
            'unit_id' => $apar->unit_id,
            'security_id' => $security->id,
            'inspected_at' => $attributes['inspected_at'] ?? '2026-06-15 10:00:00',
            'result' => $attributes['result'] ?? 'ok',
            'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : null,
            'unit_name_snapshot' => $attributes['unit_name_snapshot'] ?? $unit->unit_name,
            'unit_code_snapshot' => $attributes['unit_code_snapshot'] ?? $unit->unit_code,
            'apar_code_snapshot' => $attributes['apar_code_snapshot'] ?? $apar->apar_code,
            'apar_type_snapshot' => $attributes['apar_type_snapshot'] ?? $apar->apar_type,
            'brand_snapshot' => $attributes['brand_snapshot'] ?? $apar->brand,
            'capacity_snapshot' => $attributes['capacity_snapshot'] ?? $apar->capacity,
            'serial_number_snapshot' => $attributes['serial_number_snapshot'] ?? $apar->serial_number,
            'placement_snapshot' => $attributes['placement_snapshot'] ?? $apar->placement,
            'apar_expiry_date_snapshot' => $attributes['apar_expiry_date_snapshot'] ?? $apar->expiry_date,
        ]);

        foreach (AssetInspectionRules::aparChecklist() as $idx => $def) {
            $defaultGood = $inspection->result === 'ok' ? true : ($idx !== 0);
            $isGood = array_key_exists('force_all_good', $attributes) ? (bool) $attributes['force_all_good'] : $defaultGood;
            $note = null;
            if (! $isGood) {
                $note = $attributes['item_note'] ?? 'Catatan item';
            }
            AparInspectionItem::query()->create([
                'apar_inspection_id' => $inspection->id,
                'item_name' => $def['item_name'],
                'item_label' => $def['item_label'],
                'is_good' => $isGood,
                'note' => $note,
                'sort_order' => $def['sort_order'],
            ]);
        }

        return $inspection->fresh(['items', 'security', 'apar']);
    }

    public function test_qr_pdf_generated_with_correct_uuid_and_does_not_create_new_uuid(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $uuid = (string) Str::uuid();
        $apar = $this->createApar($unit, ['apar_code' => 'APR-QR1', 'qr_code' => $uuid]);

        $this->mock(PatrolPointQrCodeService::class, function (MockInterface $mock) use ($uuid) {
            $mock->shouldReceive('dataUri')
                ->once()
                ->with($uuid)
                ->andReturn($this->fakePngDataUri());
        });

        $response = $this->actingAs($admin)->get(route('admin.apar.qr-pdf', $apar->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringContainsString('/Subtype /Image', $content);
        $this->assertStringContainsString(
            'qr-apar-APR-QR1.pdf',
            (string) $response->headers->get('content-disposition')
        );

        $apar->refresh();
        $this->assertSame($uuid, $apar->qr_code);
        $this->assertDatabaseCount('apars', 1);
        $this->assertDatabaseCount('apar_inspections', 0);
    }

    public function test_qr_sheet_view_uses_stored_uuid_and_identity(): void
    {
        $unit = $this->createUnit();
        $uuid = (string) Str::uuid();
        $apar = $this->createApar($unit, [
            'apar_code' => 'APR-SHEET',
            'qr_code' => $uuid,
            'placement' => 'Lobby Utama',
        ]);
        $apar->load('unit');

        $html = view('admin.apar.qr-pdf', [
            'apar' => $apar,
            'aparTypeLabel' => 'CO2',
            'qrDataUri' => $this->fakePngDataUri(),
            'printedAt' => '01-01-2026 08:00 WIB',
        ])->render();

        $this->assertStringContainsString('APR-SHEET', $html);
        $this->assertStringContainsString('Lobby Utama', $html);
        $this->assertStringContainsString($unit->unit_name, $html);
        $this->assertStringContainsString($uuid, $html);
        $this->assertStringContainsString($this->fakePngDataUri(), $html);
    }

    private function fakePngDataUri(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }

    public function test_qr_pdf_authorization_and_unit_scoping(): void
    {
        $root = $this->createUnit();
        $child = $this->createUnit('ulp', $root);
        $other = $this->createUnit();
        $admin = $this->createAdmin($child);
        $aparChild = $this->createApar($child);
        $aparOther = $this->createApar($other);

        $this->actingAs($admin)->get(route('admin.apar.qr-pdf', $aparChild->id))->assertOk();
        $this->actingAs($admin)->get(route('admin.apar.qr-pdf', $aparOther->id))->assertForbidden();

        $secUnit = $this->createUnit();
        $security = $this->createSecurity($secUnit);
        $ownApar = $this->createApar($secUnit);
        $foreignApar = $this->createApar($other);

        $this->actingAs($security->user)->get(route('app.apar.qr-pdf', $ownApar->id))->assertOk();
        $this->actingAs($security->user)->get(route('app.apar.qr-pdf', $foreignApar->id))->assertNotFound();
    }

    public function test_annual_card_shows_identity_12_months_10_checklist_and_all_records_without_overwrite(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $secA = $this->createSecurity($unit, 'Petugas Alpha');
        $secB = $this->createSecurity($unit, 'Petugas Beta');
        $apar = $this->createApar($unit, ['apar_code' => 'APR-ANNUAL', 'brand' => 'BrandY', 'placement' => 'Panel A']);

        $this->createInspection($apar, $secA, ['inspected_at' => '2026-03-05 08:00:00', 'result' => 'ok', 'notes' => 'Cat A', 'force_all_good' => true]);
        $this->createInspection($apar, $secB, ['inspected_at' => '2026-03-20 14:30:00', 'result' => 'not_ok', 'notes' => 'Cat B']);
        $this->createInspection($apar, $secA, ['inspected_at' => '2026-07-10 09:00:00', 'result' => 'ok', 'notes' => 'Cat Juli', 'force_all_good' => true]);
        $this->createInspection($apar, $secA, ['inspected_at' => '2025-03-01 09:00:00', 'result' => 'ok', 'notes' => 'Tahun Lain', 'force_all_good' => true]);

        $card = (new AparAnnualCardData)->build($apar, 2026);

        $this->assertSame(2026, $card['year']);
        $this->assertCount(12, $card['months']);
        $this->assertSame('Januari', $card['months'][0]['name']);
        $this->assertSame('Desember', $card['months'][11]['name']);
        $this->assertCount(2, $card['inspectionsByMonth'][3]);
        $this->assertCount(1, $card['inspectionsByMonth'][7]);

        // Year filter excludes records from other years.
        $this->assertArrayNotHasKey(3, (new AparAnnualCardData)->build($apar, 2024)['inspectionsByMonth']);
        $this->assertCount(1, (new AparAnnualCardData)->build($apar, 2025)['inspectionsByMonth'][3]);

        $apar->load('unit');
        $html = view('admin.apar.annual-card', [
            'apar' => $apar,
            'aparTypeLabel' => 'CO2',
            'year' => $card['year'],
            'months' => $card['months'],
            'inspectionsByMonth' => $card['inspectionsByMonth'],
            'printedAt' => '01-01-2026 08:00 WIB',
        ])->render();

        $this->assertStringContainsString('APR-ANNUAL', $html);
        $this->assertStringContainsString('BrandY', $html);
        $this->assertStringContainsString('Panel A', $html);
        $this->assertStringContainsString($unit->unit_name, $html);
        $this->assertStringContainsString('Periode Tahun 2026', $html);

        foreach (['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $monthName) {
            $this->assertStringContainsString($monthName, $html);
        }
        foreach (AssetInspectionRules::aparChecklist() as $item) {
            $this->assertStringContainsString(e($item['item_label']), $html);
        }

        // both March inspections and different officers present (no overwrite/last-only)
        $this->assertStringContainsString('Petugas Alpha', $html);
        $this->assertStringContainsString('Petugas Beta', $html);
        $this->assertStringContainsString('05/03/2026', $html);
        $this->assertStringContainsString('20/03/2026', $html);
        $this->assertStringContainsString('Cat A', $html);
        $this->assertStringContainsString('Cat B', $html);
        $this->assertStringContainsString('10/07/2026', $html);
        $this->assertStringNotContainsString('Tahun Lain', $html);
        $this->assertSame(1, substr_count($html, 'rowspan="2"'));

        $response = $this->actingAs($admin)->get(route('admin.apar.annual-card', ['id' => $apar->id, 'year' => 2026]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString(
            'kartu-inspeksi-apar-APR-ANNUAL-2026.pdf',
            (string) $response->headers->get('content-disposition')
        );

        $this->assertDatabaseCount('apar_inspections', 4); // unchanged
    }

    public function test_excel_export_creates_one_row_per_inspection_with_snapshot_checklist_and_filters(): void
    {
        Excel::fake();
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $secA = $this->createSecurity($unit, 'Petugas Alpha');
        $secB = $this->createSecurity($unit, 'Petugas Beta');
        $apar = $this->createApar($unit, ['apar_code' => 'APR-EXP', 'brand' => 'CURRENT-BRAND']);

        $i1 = $this->createInspection($apar, $secA, ['inspected_at' => '2026-03-05 08:00:00', 'result' => 'ok', 'notes' => 'N1', 'brand_snapshot' => 'SNAP-BRAND', 'force_all_good' => true]);
        $i2 = $this->createInspection($apar, $secB, ['inspected_at' => '2026-03-20 14:30:00', 'result' => 'not_ok', 'notes' => 'N2']);
        $i3 = $this->createInspection($apar, $secA, ['inspected_at' => '2026-04-01 09:00:00', 'result' => 'ok', 'notes' => 'N3', 'force_all_good' => true]);

        $apar->update(['brand' => 'MUTATED-BRAND']);
        $before = AparInspection::query()->pluck('updated_at')->map(fn ($d) => (string) $d)->all();

        $this->actingAs($admin)->get(route('admin.apar.inspections.export', [
            'apar_id' => $apar->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'result' => 'not_ok',
        ]))->assertOk();

        $expectedName = 'riwayat-inspeksi-apar-apar-' . $apar->id . '-2026-03-01-sd-2026-03-31.xlsx';
        Excel::assertDownloaded($expectedName);

        Excel::assertDownloaded($expectedName, function ($export) use ($i2) {
            if (! $export instanceof AparInspectionExport) {
                return false;
            }
            $rows = $export->query()->get();
            if ($rows->count() !== 1 || (int) $rows->first()->id !== (int) $i2->id) {
                return false;
            }
            $mapped = $export->map($rows->first());
            $this->assertSame($i2->id, $mapped[0]);
            $this->assertSame($i2->apar_code_snapshot, $mapped[1]);
            $this->assertSame('Petugas Beta', $mapped[10]);
            $this->assertSame('Tidak Baik (NOT OK)', $mapped[12]);
            $this->assertSame('N2', $mapped[13]);
            $this->assertCount(14 + count(AssetInspectionRules::aparChecklist()), $mapped);
            // checklist 10 items represented
            $this->assertSame(10, count(array_slice($mapped, 14)));

            $headings = $export->headings();
            $this->assertContains('Indikator Tekanan', $headings);
            $this->assertContains('Masa Berlaku', $headings);

            return true;
        });

        $after = AparInspection::query()->pluck('updated_at')->map(fn ($d) => (string) $d)->all();
        $this->assertSame($before, $after);

        // all records in same month represented when unfiltered by result
        Excel::fake();
        $this->actingAs($admin)->get(route('admin.apar.inspections.export', [
            'apar_id' => $apar->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]))->assertOk();
        $expectedAllMarch = 'riwayat-inspeksi-apar-apar-' . $apar->id . '-2026-03-01-sd-2026-03-31.xlsx';
        Excel::assertDownloaded($expectedAllMarch, function ($export) {
            return $export instanceof AparInspectionExport && $export->query()->count() === 2;
        });
    }

    public function test_excel_export_authorization_and_unit_scoping(): void
    {
        Carbon::setTestNow('2026-11-16 10:00:00');

        try {
            Excel::fake();
            $root = $this->createUnit();
            $child = $this->createUnit('ulp', $root);
            $other = $this->createUnit();
            $admin = $this->createAdmin($child);
            $secChild = $this->createSecurity($child);
            $secOther = $this->createSecurity($other);
            $aparChild = $this->createApar($child, ['apar_code' => 'APR-CH']);
            $aparOther = $this->createApar($other, ['apar_code' => 'APR-OT']);
            $this->createInspection($aparChild, $secChild);
            $this->createInspection($aparOther, $secOther);

            $this->actingAs($admin)->get(route('admin.apar.inspections.export', ['apar_id' => $aparChild->id]))->assertOk();
            $expectedChild = 'riwayat-inspeksi-apar-apar-' . $aparChild->id . '-' . now()->timezone('Asia/Jakarta')->format('Ymd-His') . '.xlsx';
            Excel::assertDownloaded($expectedChild, function ($export) {
                return $export instanceof AparInspectionExport && $export->query()->count() === 1;
            });

            Excel::fake();
            $this->actingAs($admin)->get(route('admin.apar.inspections.export', ['apar_id' => $aparOther->id]))->assertForbidden();

            // security: export endpoint removed
            $security = $this->createSecurity($this->createUnit());
            $this->actingAs($security->user)->get('/app/apar/inspections/export?apar_id=' . $aparOther->id)->assertNotFound();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_annual_card_authorization_security_own_unit_only(): void
    {
        $unit = $this->createUnit();
        $other = $this->createUnit();
        $security = $this->createSecurity($unit);
        $ownApar = $this->createApar($unit);
        $foreignApar = $this->createApar($other);

        $this->actingAs($security->user)->get(route('app.apar.annual-card', ['id' => $ownApar->id, 'year' => 2026]))->assertOk();
        $this->actingAs($security->user)->get(route('app.apar.annual-card', ['id' => $foreignApar->id, 'year' => 2026]))->assertNotFound();
    }

    public function test_pdf_and_excel_do_not_mutate_history_for_inactive_apar(): void
    {
        Excel::fake();
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit, ['is_active' => false]);
        $inspection = $this->createInspection($apar, $security, ['inspected_at' => '2026-05-10 08:00:00', 'force_all_good' => true]);

        $beforeCount = AparInspection::query()->count();
        $beforeUpdated = $inspection->fresh()->updated_at?->toDateTimeString();

        $this->actingAs($admin)->get(route('admin.apar.qr-pdf', $apar->id))->assertOk();
        $this->actingAs($admin)->get(route('admin.apar.annual-card', ['id' => $apar->id, 'year' => 2026]))->assertOk();
        $this->actingAs($admin)->get(route('admin.apar.inspections.export', ['apar_id' => $apar->id]))->assertOk();

        $this->assertSame($beforeCount, AparInspection::query()->count());
        $this->assertSame($beforeUpdated, $inspection->fresh()->updated_at?->toDateTimeString());
        $this->assertDatabaseCount('apar_inspection_items', 10);
    }
}
