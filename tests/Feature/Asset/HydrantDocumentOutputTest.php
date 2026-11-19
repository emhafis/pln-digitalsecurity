<?php

namespace Tests\Feature\Asset;

use App\Exports\HydrantInspectionExport;
use App\Models\Hydrant;
use App\Models\HydrantInspection;
use App\Models\HydrantInspectionItem;
use App\Models\Security;
use App\Models\Unit;
use App\Services\PatrolPointQrCodeService;
use App\Support\AssetInspectionRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Mockery\MockInterface;
use Tests\Feature\Scheduling\SchedulingTestCase;

class HydrantDocumentOutputTest extends SchedulingTestCase
{
    private function createHydrant(Unit $unit, array $attributes = []): Hydrant
    {
        return Hydrant::query()->create(array_merge([
            'unit_id' => $unit->id,
            'hydrant_code' => 'HYD-'.strtoupper(Str::random(6)),
            'name' => 'Hydrant Utama',
            'hydrant_type' => 'hydrant_box',
            'brand' => 'BrandX',
            'model' => 'ModelX',
            'serial_number' => 'SN-001',
            'procurement_date' => null,
            'installation_date' => null,
            'last_maintenance_date' => '2026-05-01',
            'placement' => 'Lobby',
            'qr_code' => (string) Str::uuid(),
            'photo' => null,
            'is_active' => true,
        ], $attributes));
    }

    private function createInspection(Hydrant $hydrant, Security $security, array $attributes = []): HydrantInspection
    {
        $unit = $hydrant->unit ?? Unit::query()->find($hydrant->unit_id);
        $inspection = HydrantInspection::query()->create([
            'hydrant_id' => $hydrant->id,
            'unit_id' => $hydrant->unit_id,
            'security_id' => $security->id,
            'inspected_at' => $attributes['inspected_at'] ?? '2026-06-15 10:00:00',
            'result' => $attributes['result'] ?? 'ok',
            'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : null,
            'unit_name_snapshot' => $attributes['unit_name_snapshot'] ?? $unit->unit_name,
            'unit_code_snapshot' => $attributes['unit_code_snapshot'] ?? $unit->unit_code,
            'hydrant_code_snapshot' => $attributes['hydrant_code_snapshot'] ?? $hydrant->hydrant_code,
            'name_snapshot' => $attributes['name_snapshot'] ?? $hydrant->name,
            'hydrant_type_snapshot' => $attributes['hydrant_type_snapshot'] ?? $hydrant->hydrant_type,
            'brand_snapshot' => $attributes['brand_snapshot'] ?? $hydrant->brand,
            'model_snapshot' => $attributes['model_snapshot'] ?? $hydrant->model,
            'serial_number_snapshot' => $attributes['serial_number_snapshot'] ?? $hydrant->serial_number,
            'placement_snapshot' => $attributes['placement_snapshot'] ?? $hydrant->placement,
            'last_maintenance_date_snapshot' => $attributes['last_maintenance_date_snapshot'] ?? $hydrant->last_maintenance_date,
        ]);

        foreach (AssetInspectionRules::hydrantChecklist() as $idx => $def) {
            $defaultGood = $inspection->result === 'ok' ? true : ($idx !== 0);
            $isGood = array_key_exists('force_all_good', $attributes) ? (bool) $attributes['force_all_good'] : $defaultGood;
            $note = null;
            if (! $isGood) {
                $note = $attributes['item_note'] ?? 'Catatan item';
            }
            HydrantInspectionItem::query()->create([
                'hydrant_inspection_id' => $inspection->id,
                'item_name' => $def['item_name'],
                'item_label' => $def['item_label'],
                'is_good' => $isGood,
                'note' => $note,
                'sort_order' => $def['sort_order'],
            ]);
        }

        return $inspection->fresh(['items', 'security', 'hydrant']);
    }

    private function fakePngDataUri(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }

    public function test_qr_pdf_generated_with_correct_uuid_and_does_not_create_new_uuid(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $uuid = (string) Str::uuid();
        $hydrant = $this->createHydrant($unit, ['hydrant_code' => 'HYD-QR1', 'qr_code' => $uuid]);

        $this->mock(PatrolPointQrCodeService::class, function (MockInterface $mock) use ($uuid) {
            $mock->shouldReceive('dataUri')
                ->once()
                ->with($uuid)
                ->andReturn($this->fakePngDataUri());
        });

        $response = $this->actingAs($admin)->get(route('admin.hydrant.qr-pdf', $hydrant->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringContainsString('/Subtype /Image', $content);
        $this->assertStringContainsString(
            'qr-hydrant-HYD-QR1.pdf',
            (string) $response->headers->get('content-disposition')
        );

        $hydrant->refresh();
        $this->assertSame($uuid, $hydrant->qr_code);
        $this->assertDatabaseCount('hydrants', 1);
        $this->assertDatabaseCount('hydrant_inspections', 0);
    }

    public function test_qr_sheet_view_uses_stored_uuid_and_identity(): void
    {
        $unit = $this->createUnit();
        $uuid = (string) Str::uuid();
        $hydrant = $this->createHydrant($unit, [
            'hydrant_code' => 'HYD-SHEET',
            'name' => 'Hydrant Sheet',
            'hydrant_type' => 'siamese_connection',
            'qr_code' => $uuid,
            'placement' => 'Lobby Utama',
        ]);
        $hydrant->load('unit');

        $html = view('admin.hydrant.qr-pdf', [
            'hydrant' => $hydrant,
            'hydrantTypeLabel' => 'Siamese Connection',
            'qrDataUri' => $this->fakePngDataUri(),
            'printedAt' => '01-01-2026 08:00 WIB',
        ])->render();

        $this->assertStringContainsString('HYD-SHEET', $html);
        $this->assertStringContainsString('Hydrant Sheet', $html);
        $this->assertStringContainsString('Siamese Connection', $html);
        $this->assertStringContainsString('Lobby Utama', $html);
        $this->assertStringContainsString($unit->unit_name, $html);
        $this->assertStringContainsString($uuid, $html);
        $this->assertStringContainsString($this->fakePngDataUri(), $html);
    }

    public function test_qr_pdf_authorization_and_unit_scoping(): void
    {
        $root = $this->createUnit();
        $child = $this->createUnit('ulp', $root);
        $other = $this->createUnit();
        $admin = $this->createAdmin($child);
        $hydrantChild = $this->createHydrant($child);
        $hydrantOther = $this->createHydrant($other);

        $this->actingAs($admin)->get(route('admin.hydrant.qr-pdf', $hydrantChild->id))->assertOk();
        $this->actingAs($admin)->get(route('admin.hydrant.qr-pdf', $hydrantOther->id))->assertForbidden();

        $secUnit = $this->createUnit();
        $security = $this->createSecurity($secUnit);
        $ownHydrant = $this->createHydrant($secUnit);
        $foreignHydrant = $this->createHydrant($other);

        $this->actingAs($security->user)->get(route('app.hydrant.qr-pdf', $ownHydrant->id))->assertOk();
        $this->actingAs($security->user)->get(route('app.hydrant.qr-pdf', $foreignHydrant->id))->assertNotFound();
    }

    public function test_excel_export_creates_one_row_per_inspection_with_snapshot_checklist_and_filters(): void
    {
        Excel::fake();
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $secA = $this->createSecurity($unit, 'Petugas Alpha');
        $secB = $this->createSecurity($unit, 'Petugas Beta');
        $hydrant = $this->createHydrant($unit, ['hydrant_code' => 'HYD-EXP', 'brand' => 'CURRENT-BRAND']);

        $i1 = $this->createInspection($hydrant, $secA, ['inspected_at' => '2026-03-05 08:00:00', 'result' => 'ok', 'notes' => 'N1', 'brand_snapshot' => 'SNAP-BRAND', 'force_all_good' => true]);
        $i2 = $this->createInspection($hydrant, $secB, ['inspected_at' => '2026-03-20 14:30:00', 'result' => 'not_ok', 'notes' => 'N2']);
        $i3 = $this->createInspection($hydrant, $secA, ['inspected_at' => '2026-04-01 09:00:00', 'result' => 'ok', 'notes' => 'N3', 'force_all_good' => true]);

        $hydrant->update(['brand' => 'MUTATED-BRAND']);
        $before = HydrantInspection::query()->pluck('updated_at')->map(fn ($d) => (string) $d)->all();

        $this->actingAs($admin)->get(route('admin.hydrant.inspections.export', [
            'hydrant_id' => $hydrant->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'result' => 'not_ok',
        ]))->assertOk();

        $expectedName = 'riwayat-inspeksi-hydrant-hydrant-'.$hydrant->id.'-2026-03-01-sd-2026-03-31.xlsx';
        Excel::assertDownloaded($expectedName);

        Excel::assertDownloaded($expectedName, function ($export) use ($i2) {
            if (! $export instanceof HydrantInspectionExport) {
                return false;
            }
            $rows = $export->query()->get();
            if ($rows->count() !== 1 || (int) $rows->first()->id !== (int) $i2->id) {
                return false;
            }
            $mapped = $export->map($rows->first());
            $this->assertSame($i2->id, $mapped[0]);
            $this->assertSame($i2->hydrant_code_snapshot, $mapped[1]);
            $this->assertSame('Petugas Beta', $mapped[11]);
            $this->assertSame('Tidak Baik (NOT OK)', $mapped[13]);
            $this->assertSame('N2', $mapped[14]);
            $this->assertCount(15 + count(AssetInspectionRules::hydrantChecklist()), $mapped);
            $this->assertSame(11, count(array_slice($mapped, 15)));

            $headings = $export->headings();
            $this->assertContains('Kondisi Baterai', $headings);
            $this->assertContains('Pipa dan Sambungan', $headings);

            return true;
        });

        $after = HydrantInspection::query()->pluck('updated_at')->map(fn ($d) => (string) $d)->all();
        $this->assertSame($before, $after);

        Excel::fake();
        $this->actingAs($admin)->get(route('admin.hydrant.inspections.export', [
            'hydrant_id' => $hydrant->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]))->assertOk();
        Excel::assertDownloaded($expectedName, function ($export) {
            return $export instanceof HydrantInspectionExport && $export->query()->count() === 2;
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
            $hydrantChild = $this->createHydrant($child, ['hydrant_code' => 'HYD-CH']);
            $hydrantOther = $this->createHydrant($other, ['hydrant_code' => 'HYD-OT']);
            $this->createInspection($hydrantChild, $secChild);
            $this->createInspection($hydrantOther, $secOther);

            $this->actingAs($admin)->get(route('admin.hydrant.inspections.export', ['hydrant_id' => $hydrantChild->id]))->assertOk();
            $expectedChild = 'riwayat-inspeksi-hydrant-hydrant-'.$hydrantChild->id.'-'.now()->timezone('Asia/Jakarta')->format('Ymd-His').'.xlsx';
            Excel::assertDownloaded($expectedChild, function ($export) {
                return $export instanceof HydrantInspectionExport && $export->query()->count() === 1;
            });

            Excel::fake();
            $this->actingAs($admin)->get(route('admin.hydrant.inspections.export', ['hydrant_id' => $hydrantOther->id]))->assertForbidden();

            $security = $this->createSecurity($this->createUnit());
            $this->actingAs($security->user)->get('/app/hydrant/inspections/export?hydrant_id=' . $hydrantOther->id)->assertNotFound();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_pdf_and_excel_do_not_mutate_history_for_inactive_hydrant(): void
    {
        Excel::fake();
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit, ['is_active' => false]);
        $inspection = $this->createInspection($hydrant, $security, ['inspected_at' => '2026-05-10 08:00:00', 'force_all_good' => true]);

        $beforeCount = HydrantInspection::query()->count();
        $beforeUpdated = $inspection->fresh()->updated_at?->toDateTimeString();

        $this->actingAs($admin)->get(route('admin.hydrant.qr-pdf', $hydrant->id))->assertOk();
        $this->actingAs($admin)->get(route('admin.hydrant.inspections.export', ['hydrant_id' => $hydrant->id]))->assertOk();

        $this->assertSame($beforeCount, HydrantInspection::query()->count());
        $this->assertSame($beforeUpdated, $inspection->fresh()->updated_at?->toDateTimeString());
        $this->assertDatabaseCount('hydrant_inspection_items', 11);
    }
}
