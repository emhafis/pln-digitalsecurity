<?php

namespace Tests\Feature\App;

use App\Models\Apar;
use App\Models\AparInspection;
use App\Models\AparInspectionItem;
use App\Models\AparInspectionPhoto;
use App\Models\Security;
use App\Models\Unit;
use App\Support\AssetInspectionPhotoStorage;
use App\Support\AssetInspectionRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Scheduling\SchedulingTestCase;

class AparInspectionSubmitTest extends SchedulingTestCase
{
    private function createApar(Unit $unit, array $attributes = []): Apar
    {
        return Apar::query()->create(array_merge([
            'unit_id' => $unit->id,
            'apar_code' => 'APR-' . strtoupper(Str::random(6)),
            'apar_type' => 'co2',
            'brand' => 'BrandX',
            'capacity' => '6 Kg',
            'placement' => 'Lobby',
            'qr_code' => (string) Str::uuid(),
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
        ], AssetInspectionRules::aparChecklist());
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
        }, AssetInspectionRules::aparChecklist());
    }

    public function test_active_security_can_open_inspection_form_for_own_unit_active_apar(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit, ['apar_code' => 'APR-FORM']);

        $response = $this->actingAs($security->user)->get("/app/apar/{$apar->id}/inspect");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Apar/CreateInspection')
            ->where('apar.id', $apar->id)
            ->where('apar.apar_code', 'APR-FORM')
            ->where('maxPhotos', 5)
            ->has('checklist', 10)
        );
    }

    public function test_security_can_submit_all_good_apar_inspection_creating_new_record(): void
    {
        Carbon::setTestNow('2026-11-16 10:15:30');
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit, 'Petugas A');
        $apar = $this->createApar($unit, [
            'apar_code' => 'APR-OK1',
            'unit_id' => $unit->id,
        ]);

        $response = $this->actingAs($security->user)->post("/app/apar/{$apar->id}/inspect", [
            'items' => $this->allGoodItems(),
            'notes' => null,
        ]);

        $response->assertRedirect(route('app.apar.show', $apar->id));
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('apar_inspections', 1);
        $inspection = AparInspection::query()->firstOrFail();
        $this->assertSame($apar->id, $inspection->apar_id);
        $this->assertSame($unit->id, $inspection->unit_id);
        $this->assertSame($security->id, $inspection->security_id);
        $this->assertSame('ok', $inspection->result);
        $this->assertNull($inspection->notes);
        $this->assertSame('2026-11-16 10:15:30', $inspection->inspected_at?->format('Y-m-d H:i:s'));
        $this->assertSame($apar->apar_code, $inspection->apar_code_snapshot);
        $this->assertSame($unit->unit_name, $inspection->unit_name_snapshot);
        $this->assertSame($apar->apar_type, $inspection->apar_type_snapshot);

        $this->assertDatabaseCount('apar_inspection_items', 10);
        $this->assertSame(10, AparInspectionItem::query()->where('apar_inspection_id', $inspection->id)->where('is_good', true)->count());

        Carbon::setTestNow();
    }

    public function test_each_submit_creates_new_inspection_record_without_overwriting_history(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit);

        Carbon::setTestNow('2026-11-16 08:00:00');
        $this->actingAs($security->user)->post("/app/apar/{$apar->id}/inspect", [
            'items' => $this->allGoodItems(),
        ])->assertRedirect();

        Carbon::setTestNow('2026-11-16 09:00:00');
        $items = $this->itemsWithNotGood(['pressure_gauge']);
        $this->actingAs($security->user)->post("/app/apar/{$apar->id}/inspect", [
            'items' => $items,
            'notes' => 'Tekanan turun',
        ])->assertRedirect();

        $this->assertDatabaseCount('apar_inspections', 2);
        $results = AparInspection::query()->orderBy('id')->pluck('result')->all();
        $this->assertSame(['ok', 'not_ok'], $results);
        $this->assertDatabaseCount('apar_inspection_items', 20);

        Carbon::setTestNow();
    }

    public function test_not_ok_result_requires_notes_and_rejects_empty_notes(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit);

        $response = $this->actingAs($security->user)->from("/app/apar/{$apar->id}/inspect")
            ->post("/app/apar/{$apar->id}/inspect", [
                'items' => $this->itemsWithNotGood(['hose']),
                'notes' => '',
            ]);

        $response->assertRedirect("/app/apar/{$apar->id}/inspect");
        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('apar_inspections', 0);
        $this->assertDatabaseCount('apar_inspection_items', 0);
    }

    public function test_more_than_five_photos_is_rejected(): void
    {
        Storage::fake('local');
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit);

        $photos = [];
        for ($i = 0; $i < 6; $i++) {
            $photos[] = UploadedFile::fake()->image("p{$i}.jpg");
        }

        $response = $this->actingAs($security->user)->from("/app/apar/{$apar->id}/inspect")
            ->post("/app/apar/{$apar->id}/inspect", [
                'items' => $this->allGoodItems(),
                'photos' => $photos,
            ]);

        $response->assertRedirect("/app/apar/{$apar->id}/inspect");
        $response->assertSessionHasErrors('photos');
        $this->assertDatabaseCount('apar_inspections', 0);
        $this->assertSame([], Storage::disk('local')->allFiles(AssetInspectionPhotoStorage::BASE_DIRECTORY));
    }

    public function test_valid_photos_are_stored_privately_and_recorded(): void
    {
        Storage::fake('local');
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit);

        $photos = [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
        ];

        $response = $this->actingAs($security->user)->post("/app/apar/{$apar->id}/inspect", [
            'items' => $this->allGoodItems(),
            'photos' => $photos,
        ]);

        $response->assertRedirect(route('app.apar.show', $apar->id));
        $this->assertDatabaseCount('apar_inspections', 1);
        $inspection = AparInspection::query()->firstOrFail();
        $this->assertDatabaseCount('apar_inspection_photos', 2);

        $paths = AparInspectionPhoto::query()->where('apar_inspection_id', $inspection->id)->pluck('photo_path')->all();
        $this->assertCount(2, $paths);
        foreach ($paths as $path) {
            $this->assertStringStartsWith('asset-inspections/apar/'.$apar->id.'/', $path);
            $this->assertTrue(Storage::disk('local')->exists($path));
            $this->assertFalse(Storage::disk('public')->exists($path));
        }
    }

    public function test_security_cannot_submit_inspection_for_apar_in_other_unit(): void
    {
        $unit = $this->createUnit();
        $other = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($other);

        $this->actingAs($security->user)
            ->get("/app/apar/{$apar->id}/inspect")
            ->assertNotFound();

        $this->actingAs($security->user)
            ->post("/app/apar/{$apar->id}/inspect", ['items' => $this->allGoodItems()])
            ->assertNotFound();

        $this->assertDatabaseCount('apar_inspections', 0);
    }

    public function test_security_cannot_submit_inspection_for_inactive_apar(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit, ['is_active' => false]);

        $this->actingAs($security->user)
            ->get("/app/apar/{$apar->id}/inspect")
            ->assertNotFound();

        $this->actingAs($security->user)
            ->post("/app/apar/{$apar->id}/inspect", ['items' => $this->allGoodItems()])
            ->assertNotFound();

        $this->assertDatabaseCount('apar_inspections', 0);
    }

    public function test_inactive_security_cannot_submit_inspection(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit);
        $security->user->update(['is_active' => false]);

        $this->actingAs($security->user)
            ->get("/app/apar/{$apar->id}/inspect")
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseCount('apar_inspections', 0);
    }

    public function test_incomplete_checklist_is_rejected(): void
    {
        $unit = $this->createUnit();
        $security = $this->createSecurity($unit);
        $apar = $this->createApar($unit);

        $items = $this->allGoodItems();
        array_pop($items);

        $response = $this->actingAs($security->user)->from("/app/apar/{$apar->id}/inspect")
            ->post("/app/apar/{$apar->id}/inspect", [
                'items' => $items,
            ]);

        $response->assertRedirect("/app/apar/{$apar->id}/inspect");
        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('apar_inspections', 0);
    }
}
