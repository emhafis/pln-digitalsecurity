<?php

namespace Tests\Unit;

use App\Models\Apar;
use App\Models\Hydrant;
use App\Models\Security;
use App\Models\Unit;
use App\Models\User;
use App\Support\AssetInspectionPhotoStorage;
use App\Support\AssetInspectionRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetInspectionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_apar_checklist_has_ten_required_items_in_order(): void
    {
        $items = AssetInspectionRules::aparChecklist();

        $this->assertCount(10, $items);
        $this->assertSame([
            'pressure_gauge',
            'operating_lever',
            'safety_pin_seal',
            'nozzle',
            'hose',
            'discharge_orifice',
            'carrying_handle',
            'body',
            'label',
            'expiry_date',
        ], array_column($items, 'item_name'));
        $this->assertSame(range(1, 10), array_column($items, 'sort_order'));
    }

    public function test_hydrant_checklist_has_eleven_required_items_in_order(): void
    {
        $items = AssetInspectionRules::hydrantChecklist();

        $this->assertCount(11, $items);
        $this->assertSame([
            'kondisi_baterai',
            'kondisi_pelumas',
            'kondisi_air_radiator',
            'kondisi_bahan_bakar',
            'pemanasan',
            'kondisi_mesin',
            'pressure_gauge',
            'kebocoran',
            'panel_indikator',
            'valve',
            'pipa_sambungan',
        ], array_column($items, 'item_name'));
        $this->assertSame(range(1, 11), array_column($items, 'sort_order'));
    }

    public function test_result_is_ok_only_when_all_items_good_and_not_ok_if_any_not_good(): void
    {
        $allGood = [
            ['is_good' => true],
            ['is_good' => true],
        ];
        $oneBad = [
            ['is_good' => true],
            ['is_good' => false],
        ];

        $this->assertSame(AssetInspectionRules::RESULT_OK, AssetInspectionRules::resultForItems($allGood));
        $this->assertSame(AssetInspectionRules::RESULT_NOT_OK, AssetInspectionRules::resultForItems($oneBad));
    }

    public function test_notes_required_when_result_not_ok(): void
    {
        $this->assertNotEmpty(AssetInspectionRules::validateNotesForResult(AssetInspectionRules::RESULT_NOT_OK, null));
        $this->assertNotEmpty(AssetInspectionRules::validateNotesForResult(AssetInspectionRules::RESULT_NOT_OK, '   '));
        $this->assertSame([], AssetInspectionRules::validateNotesForResult(AssetInspectionRules::RESULT_NOT_OK, 'Ada kerusakan'));
        $this->assertSame([], AssetInspectionRules::validateNotesForResult(AssetInspectionRules::RESULT_OK, null));
    }

    public function test_checklist_completeness_requires_all_items_exactly_once(): void
    {
        $complete = array_map(fn ($n) => ['item_name' => $n, 'is_good' => true], AssetInspectionRules::checklistItemNames('apar'));
        $missing = array_slice($complete, 1);
        $duplicate = $complete;
        $duplicate[] = ['item_name' => 'pressure_gauge', 'is_good' => false];

        $this->assertSame([], AssetInspectionRules::validateChecklistCompleteness('apar', $complete));
        $this->assertNotEmpty(AssetInspectionRules::validateChecklistCompleteness('apar', $missing));
        $this->assertNotEmpty(AssetInspectionRules::validateChecklistCompleteness('apar', $duplicate));
    }

    public function test_normalize_items_returns_full_ordered_checklist_for_persistence(): void
    {
        $submitted = [
            ['item_name' => 'body', 'is_good' => false, 'note' => ' penyok '],
            ['item_name' => 'pressure_gauge', 'is_good' => true, 'note' => ''],
        ];
        // submit only two but normalize fills all in defined order
        $normalized = AssetInspectionRules::normalizeItems('apar', $submitted);

        $this->assertCount(10, $normalized);
        $this->assertSame('pressure_gauge', $normalized[0]['item_name']);
        $this->assertTrue($normalized[0]['is_good']);
        $this->assertNull($normalized[0]['note']);
        $body = collect($normalized)->firstWhere('item_name', 'body');
        $this->assertFalse($body['is_good']);
        $this->assertSame('penyok', $body['note']);
    }

    public function test_rules_limit_photos_to_five_and_require_boolean_items(): void
    {
        $rules = AssetInspectionRules::rules('hydrant');

        $this->assertContains('max:'.AssetInspectionRules::MAX_PHOTOS, $rules['photos']);
        $this->assertSame(['required', 'boolean'], $rules['items.*.is_good']);
        $this->assertSame(['required', 'array', 'size:11'], $rules['items']);
    }

    public function test_security_must_be_active_with_profile_and_same_unit_active_asset(): void
    {
        $unit = Unit::query()->create([
            'unit_name' => 'Unit A',
            'unit_level' => 'uid',
            'is_active' => true,
        ]);
        $otherUnit = Unit::query()->create([
            'unit_name' => 'Unit B',
            'unit_level' => 'uid',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'security',
            'is_active' => true,
            'unit_id' => $unit->id,
        ]);
        Security::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Satpam A',
            'kta_issued_date' => '2026-01-01',
        ]);

        $apar = Apar::query()->create([
            'unit_id' => $unit->id,
            'apar_code' => 'APR-001',
            'apar_type' => 'co2',
            'brand' => 'X',
            'capacity' => '6 Kg',
            'placement' => 'Lobby',
            'qr_code' => '11111111-1111-1111-1111-111111111111',
            'is_active' => true,
        ]);
        $inactiveApar = Apar::query()->create([
            'unit_id' => $unit->id,
            'apar_code' => 'APR-002',
            'apar_type' => 'co2',
            'brand' => 'X',
            'capacity' => '6 Kg',
            'placement' => 'Lobby',
            'qr_code' => '22222222-2222-2222-2222-222222222222',
            'is_active' => false,
        ]);
        $otherUnitApar = Apar::query()->create([
            'unit_id' => $otherUnit->id,
            'apar_code' => 'APR-003',
            'apar_type' => 'co2',
            'brand' => 'X',
            'capacity' => '6 Kg',
            'placement' => 'Lobby',
            'qr_code' => '33333333-3333-3333-3333-333333333333',
            'is_active' => true,
        ]);

        $this->assertTrue(AssetInspectionRules::isSecurityUserEligible($user));
        $this->assertTrue(AssetInspectionRules::canSecurityInspectAsset($user, $apar));
        $this->assertFalse(AssetInspectionRules::canSecurityInspectAsset($user, $inactiveApar));
        $this->assertFalse(AssetInspectionRules::canSecurityInspectAsset($user, $otherUnitApar));

        $inactiveUser = User::factory()->create(['role' => 'security', 'is_active' => false, 'unit_id' => $unit->id]);
        Security::query()->create(['user_id' => $inactiveUser->id, 'full_name' => 'Satpam B', 'kta_issued_date' => '2026-01-01']);
        $this->assertFalse(AssetInspectionRules::isSecurityUserEligible($inactiveUser));

        $noProfile = User::factory()->create(['role' => 'security', 'is_active' => true, 'unit_id' => $unit->id]);
        $this->assertFalse(AssetInspectionRules::isSecurityUserEligible($noProfile));
    }

    public function test_photo_storage_uses_public_disk_and_managed_paths(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('evidence.jpg', 100, 100);
        $path = AssetInspectionPhotoStorage::store($file, 'apar', 10, 'Asep Ramadan', 'APAR-001');

        $this->assertTrue(AssetInspectionPhotoStorage::isManagedPath($path));
        $this->assertStringStartsWith('asset-inspections/apar/10/', $path);
        $this->assertMatchesRegularExpression('/^\d{6}-\d{4}-INSP-APAR-APAR-001-ASEP-RAMADAN-[A-Z0-9]{4}\.jpg$/', basename($path));
        Storage::disk('public')->assertExists($path);

        $this->assertTrue(AssetInspectionPhotoStorage::delete($path));
        Storage::disk('public')->assertMissing($path);

        $this->assertFalse(AssetInspectionPhotoStorage::isManagedPath('public/other/file.jpg'));
        $this->assertFalse(AssetInspectionPhotoStorage::delete('../etc/passwd'));
    }
}
