<?php

namespace Tests\Feature\App;

use App\Models\Hydrant;
use App\Models\HydrantInspection;
use App\Models\HydrantInspectionItem;
use App\Models\HydrantInspectionPhoto;
use App\Models\Security;
use App\Models\Unit;
use App\Support\AssetInspectionPhotoStorage;
use App\Support\AssetInspectionRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Scheduling\SchedulingTestCase;

class HydrantInspectionSubmitTest extends SchedulingTestCase
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
            'serial_number' => null,
            'procurement_date' => null,
            'installation_date' => null,
            'last_maintenance_date' => null,
            'placement' => 'Lobby',
            'qr_code' => (string) Str::uuid(),
            'photo' => null,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @return array<int, array{item_name:string,is_good:bool,note?:?string}>
     */
    private function allGoodItems(): array
    {
        return array_map(fn ($item) => [
            'item_name' => $item['item_name'],
            'is_good' => true,
            'note' => null,
        ], AssetInspectionRules::hydrantChecklist());
    }

    /**
     * @param array<int,string> $notGoodNames
     * @return array<int, array{item_name:string,is_good:bool,note?:?string}>
     */
    private function itemsWithNotGood(array $notGoodNames): array
    {
        $notGood = array_flip($notGoodNames);

        return array_map(function ($item) use ($notGood) {
            $bad = isset($notGood[$item['item_name']]);

            return [
                'item_name' => $item['item_name'],
                'is_good' => ! $bad,
                'note' => $bad ? 'Rusak' : null,
            ];
        }, AssetInspectionRules::hydrantChecklist());
    }

    public function test_active_security_can_open_inspection_form_for_own_unit_active_hydrant(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit, ['hydrant_code' => 'HYD-FORM']);

        $response = $this->actingAs($security->user)->get("/app/hydrant/{$hydrant->id}/inspect");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Hydrant/CreateInspection')
            ->where('hydrant.id', $hydrant->id)
            ->where('hydrant.hydrant_code', 'HYD-FORM')
            ->where('maxPhotos', 5)
            ->has('checklist', 11)
        );
    }

    public function test_security_can_submit_all_good_hydrant_inspection_creating_new_record(): void
    {
        Carbon::setTestNow('2026-11-16 10:15:30');
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit, 'Petugas A');
        $hydrant = $this->createHydrant($unit, [
            'hydrant_code' => 'HYD-OK1',
            'name' => 'Hydrant OK',
            'hydrant_type' => 'jockey_pump',
            'brand' => 'BrandA',
            'model' => 'ModelA',
            'placement' => 'Pump Room',
            'last_maintenance_date' => '2026-10-01',
        ]);

        $response = $this->actingAs($security->user)->post("/app/hydrant/{$hydrant->id}/inspect", [
            'items' => $this->allGoodItems(),
            'notes' => null,
        ]);

        $response->assertRedirect(route('app.hydrant.show', $hydrant->id));
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('hydrant_inspections', 1);
        $inspection = HydrantInspection::query()->firstOrFail();
        $this->assertSame($hydrant->id, $inspection->hydrant_id);
        $this->assertSame($unit->id, $inspection->unit_id);
        $this->assertSame($security->id, $inspection->security_id);
        $this->assertSame('ok', $inspection->result);
        $this->assertNull($inspection->notes);
        $this->assertSame('2026-11-16 10:15:30', $inspection->inspected_at?->format('Y-m-d H:i:s'));
        $this->assertSame($hydrant->hydrant_code, $inspection->hydrant_code_snapshot);
        $this->assertSame($hydrant->name, $inspection->name_snapshot);
        $this->assertSame($hydrant->hydrant_type, $inspection->hydrant_type_snapshot);
        $this->assertSame($hydrant->brand, $inspection->brand_snapshot);
        $this->assertSame($hydrant->model, $inspection->model_snapshot);
        $this->assertSame($hydrant->placement, $inspection->placement_snapshot);
        $this->assertSame('2026-10-01', $inspection->last_maintenance_date_snapshot?->format('Y-m-d'));
        $this->assertSame($unit->unit_name, $inspection->unit_name_snapshot);

        $this->assertDatabaseCount('hydrant_inspection_items', 11);
        $this->assertSame(11, HydrantInspectionItem::query()->where('hydrant_inspection_id', $inspection->id)->where('is_good', true)->count());

        Carbon::setTestNow();
    }

    public function test_each_submit_creates_new_inspection_record_without_overwriting_history(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);

        Carbon::setTestNow('2026-11-16 08:00:00');
        $this->actingAs($security->user)->post("/app/hydrant/{$hydrant->id}/inspect", [
            'items' => $this->allGoodItems(),
        ])->assertRedirect();

        Carbon::setTestNow('2026-11-16 09:00:00');
        $this->actingAs($security->user)->post("/app/hydrant/{$hydrant->id}/inspect", [
            'items' => $this->itemsWithNotGood(['kebocoran']),
            'notes' => 'Ada kebocoran',
        ])->assertRedirect();

        $this->assertDatabaseCount('hydrant_inspections', 2);
        $results = HydrantInspection::query()->orderBy('id')->pluck('result')->all();
        $this->assertSame(['ok', 'not_ok'], $results);
        $this->assertDatabaseCount('hydrant_inspection_items', 22);

        Carbon::setTestNow();
    }

    public function test_not_ok_result_requires_notes_and_rejects_empty_notes(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);

        $response = $this->actingAs($security->user)->from("/app/hydrant/{$hydrant->id}/inspect")
            ->post("/app/hydrant/{$hydrant->id}/inspect", [
                'items' => $this->itemsWithNotGood(['valve']),
                'notes' => '',
            ]);

        $response->assertRedirect("/app/hydrant/{$hydrant->id}/inspect");
        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('hydrant_inspections', 0);
        $this->assertDatabaseCount('hydrant_inspection_items', 0);
    }

    public function test_more_than_five_photos_is_rejected(): void
    {
        Storage::fake('local');
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);

        $photos = [];
        for ($i = 0; $i < 6; $i++) {
            $photos[] = UploadedFile::fake()->image("p{$i}.jpg");
        }

        $response = $this->actingAs($security->user)->from("/app/hydrant/{$hydrant->id}/inspect")
            ->post("/app/hydrant/{$hydrant->id}/inspect", [
                'items' => $this->allGoodItems(),
                'photos' => $photos,
            ]);

        $response->assertRedirect("/app/hydrant/{$hydrant->id}/inspect");
        $response->assertSessionHasErrors('photos');
        $this->assertDatabaseCount('hydrant_inspections', 0);
        $this->assertSame([], Storage::disk('local')->allFiles(AssetInspectionPhotoStorage::BASE_DIRECTORY));
    }

    public function test_valid_photos_are_stored_privately_and_recorded(): void
    {
        Storage::fake('local');
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);

        $photos = [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
        ];

        $response = $this->actingAs($security->user)->post("/app/hydrant/{$hydrant->id}/inspect", [
            'items' => $this->allGoodItems(),
            'photos' => $photos,
        ]);

        $response->assertRedirect(route('app.hydrant.show', $hydrant->id));
        $this->assertDatabaseCount('hydrant_inspections', 1);
        $inspection = HydrantInspection::query()->firstOrFail();
        $this->assertDatabaseCount('hydrant_inspection_photos', 2);

        $paths = HydrantInspectionPhoto::query()->where('hydrant_inspection_id', $inspection->id)->pluck('photo_path')->all();
        $this->assertCount(2, $paths);
        foreach ($paths as $path) {
            $this->assertStringStartsWith('asset-inspections/hydrant/'.$hydrant->id.'/', $path);
            $this->assertTrue(Storage::disk('local')->exists($path));
            $this->assertFalse(Storage::disk('public')->exists($path));
        }
    }

    public function test_security_cannot_submit_inspection_for_hydrant_in_other_unit(): void
    {
        $unit = $this->createUnit();
        $other = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($other);

        $this->actingAs($security->user)
            ->get("/app/hydrant/{$hydrant->id}/inspect")
            ->assertNotFound();

        $this->actingAs($security->user)
            ->post("/app/hydrant/{$hydrant->id}/inspect", ['items' => $this->allGoodItems()])
            ->assertNotFound();

        $this->assertDatabaseCount('hydrant_inspections', 0);
    }

    public function test_security_cannot_submit_inspection_for_inactive_hydrant(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit, ['is_active' => false]);

        $this->actingAs($security->user)
            ->get("/app/hydrant/{$hydrant->id}/inspect")
            ->assertNotFound();

        $this->actingAs($security->user)
            ->post("/app/hydrant/{$hydrant->id}/inspect", ['items' => $this->allGoodItems()])
            ->assertNotFound();

        $this->assertDatabaseCount('hydrant_inspections', 0);
    }

    public function test_inactive_security_cannot_submit_inspection(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);
        $security->user->update(['is_active' => false]);

        $this->actingAs($security->user)
            ->get("/app/hydrant/{$hydrant->id}/inspect")
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseCount('hydrant_inspections', 0);
    }

    public function test_security_without_profile_cannot_open_or_submit_inspection(): void
    {
        $unit = $this->createUnit();
        $user = \App\Models\User::factory()->create([
            'role' => 'security',
            'is_active' => true,
            'unit_id' => $unit->id,
        ]);
        $hydrant = $this->createHydrant($unit);

        $this->actingAs($user)->get("/app/hydrant/{$hydrant->id}/inspect")->assertForbidden();
        $this->actingAs($user)->post("/app/hydrant/{$hydrant->id}/inspect", [
            'items' => $this->allGoodItems(),
        ])->assertForbidden();

        $this->assertDatabaseCount('hydrant_inspections', 0);
    }

    public function test_duplicate_or_unknown_checklist_items_are_rejected(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);

        $unknown = $this->allGoodItems();
        $unknown[0]['item_name'] = 'item_tidak_dikenal';

        $unknownResponse = $this->actingAs($security->user)->from("/app/hydrant/{$hydrant->id}/inspect")
            ->post("/app/hydrant/{$hydrant->id}/inspect", ['items' => $unknown]);
        $unknownResponse->assertRedirect("/app/hydrant/{$hydrant->id}/inspect");
        $unknownResponse->assertSessionHasErrors();

        $duplicate = $this->allGoodItems();
        $duplicate[1]['item_name'] = $duplicate[0]['item_name'];

        $duplicateResponse = $this->actingAs($security->user)->from("/app/hydrant/{$hydrant->id}/inspect")
            ->post("/app/hydrant/{$hydrant->id}/inspect", ['items' => $duplicate]);
        $duplicateResponse->assertRedirect("/app/hydrant/{$hydrant->id}/inspect");
        $duplicateResponse->assertSessionHasErrors();

        $this->assertDatabaseCount('hydrant_inspections', 0);
        $this->assertDatabaseCount('hydrant_inspection_items', 0);
    }

    public function test_item_condition_must_be_boolean(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);

        $items = $this->allGoodItems();
        $items[0]['is_good'] = 'mungkin';

        $response = $this->actingAs($security->user)->from("/app/hydrant/{$hydrant->id}/inspect")
            ->post("/app/hydrant/{$hydrant->id}/inspect", ['items' => $items]);

        $response->assertRedirect("/app/hydrant/{$hydrant->id}/inspect");
        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('hydrant_inspections', 0);
    }

    public function test_client_cannot_override_inspection_time_unit_or_security(): void
    {
        Carbon::setTestNow('2026-11-16 11:45:00');

        try {
            $unit = $this->createUnit();
            $other = $this->createUnit();
            $otherSecurity = $this->createSecurity($other);
            $security = $this->createSecurity($unit);
            $hydrant = $this->createHydrant($unit);

            $this->actingAs($security->user)->post("/app/hydrant/{$hydrant->id}/inspect", [
                'items' => $this->allGoodItems(),
                'inspected_at' => '2020-01-01 00:00:00',
                'unit_id' => $other->id,
                'security_id' => $otherSecurity->id,
            ])->assertRedirect();

            $inspection = HydrantInspection::query()->firstOrFail();
            $this->assertSame('2026-11-16 11:45:00', $inspection->inspected_at?->format('Y-m-d H:i:s'));
            $this->assertSame($unit->id, $inspection->unit_id);
            $this->assertSame($security->id, $inspection->security_id);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_invalid_photo_type_is_rejected(): void
    {
        Storage::fake('local');
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);

        $response = $this->actingAs($security->user)->from("/app/hydrant/{$hydrant->id}/inspect")
            ->post("/app/hydrant/{$hydrant->id}/inspect", [
                'items' => $this->allGoodItems(),
                'photos' => [UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf')],
            ]);

        $response->assertRedirect("/app/hydrant/{$hydrant->id}/inspect");
        $response->assertSessionHasErrors('photos.0');
        $this->assertDatabaseCount('hydrant_inspections', 0);
        $this->assertSame([], Storage::disk('local')->allFiles(AssetInspectionPhotoStorage::BASE_DIRECTORY));
    }

    public function test_failed_photo_record_rolls_back_and_removes_orphan_file(): void
    {
        Storage::fake('local');
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);

        DB::listen(function ($query) {
            if (str_contains($query->sql, 'insert into') && str_contains($query->sql, 'hydrant_inspection_photos')) {
                throw new \RuntimeException('forced photo insert failure');
            }
        });

        $caught = null;
        try {
            $this->actingAs($security->user)->withoutExceptionHandling()->post("/app/hydrant/{$hydrant->id}/inspect", [
                'items' => $this->allGoodItems(),
                'photos' => [UploadedFile::fake()->image('foto.jpg')],
            ]);
        } catch (\RuntimeException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'Kegagalan penyimpanan foto seharusnya dilempar ulang.');
        $this->assertDatabaseCount('hydrant_inspections', 0);
        $this->assertDatabaseCount('hydrant_inspection_items', 0);
        $this->assertDatabaseCount('hydrant_inspection_photos', 0);
        $this->assertSame([], Storage::disk('local')->allFiles(AssetInspectionPhotoStorage::BASE_DIRECTORY));
    }

    public function test_incomplete_checklist_is_rejected(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $hydrant = $this->createHydrant($unit);

        $items = $this->allGoodItems();
        array_pop($items);

        $response = $this->actingAs($security->user)->from("/app/hydrant/{$hydrant->id}/inspect")
            ->post("/app/hydrant/{$hydrant->id}/inspect", [
                'items' => $items,
            ]);

        $response->assertRedirect("/app/hydrant/{$hydrant->id}/inspect");
        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('hydrant_inspections', 0);
    }
}
