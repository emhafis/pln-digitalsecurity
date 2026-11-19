<?php

namespace Tests\Feature\Admin;

use App\Models\SchedulePeriod;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Scheduling\SchedulingTestCase;

class SchedulePeriodBulkStoreTest extends SchedulingTestCase
{
    public function test_bulk_store_one_team(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit);
        $pattern = $this->createPattern($unit);

        $response = $this->actingAs($admin)->post('/admin/schedule-period/bulk', [
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'name' => 'Bulk Period',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => SchedulePeriod::STATUS_DRAFT,
            'teams' => [
                [
                    'security_team_id' => $team->id,
                    'members' => [
                        [
                            'security_id' => $security->id,
                            'schedule_pattern_id' => $pattern->id,
                            'rotation_day' => 3,
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.schedule-period.index'));
        $response->assertSessionHasNoErrors();

        $this->assertSame(1, SchedulePeriod::query()->count());
        $period = SchedulePeriod::query()->firstOrFail();
        $this->assertSame($team->id, (int) $period->security_team_id);
        $this->assertSame(2, (int) $period->members()->firstOrFail()->start_offset);
    }

    public function test_bulk_store_multiple_teams_creates_distinct_periods(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $alpha = $this->createTeam($post, 'A');
        $bravo = $this->createTeam($post, 'B');
        $admin = $this->createAdmin($unit);
        $securityA = $this->createSecurity($unit, 'Satpam A');
        $securityB = $this->createSecurity($unit, 'Satpam B');
        $pattern = $this->createPattern($unit);

        $response = $this->actingAs($admin)->post('/admin/schedule-period/bulk', [
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'name' => 'Bulk Period',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => SchedulePeriod::STATUS_DRAFT,
            'teams' => [
                [
                    'security_team_id' => $alpha->id,
                    'members' => [
                        ['security_id' => $securityA->id, 'schedule_pattern_id' => $pattern->id, 'start_offset' => 0],
                    ],
                ],
                [
                    'security_team_id' => $bravo->id,
                    'members' => [
                        ['security_id' => $securityB->id, 'schedule_pattern_id' => $pattern->id, 'start_offset' => 4],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.schedule-period.index'));
        $response->assertSessionHasNoErrors();

        $periods = SchedulePeriod::query()->orderBy('id')->get();
        $this->assertCount(2, $periods);
        $this->assertNotSame($periods[0]->id, $periods[1]->id);
        $this->assertSame(4, (int) $periods[1]->members()->firstOrFail()->start_offset);
    }

    public function test_bulk_store_rolls_back_on_overlap(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $alpha = $this->createTeam($post, 'A');
        $bravo = $this->createTeam($post, 'B');
        $admin = $this->createAdmin($unit);

        $existingPeriod = $this->createPeriod($unit, $post, $alpha, $admin, '2026-11-01', '2026-11-30');

        $response = $this->actingAs($admin)->post('/admin/schedule-period/bulk', [
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'name' => 'Bulk Period',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => SchedulePeriod::STATUS_DRAFT,
            'teams' => [
                [
                    'security_team_id' => $alpha->id,
                    'members' => [],
                ],
                [
                    'security_team_id' => $bravo->id,
                    'members' => [],
                ],
            ],
        ]);

        $response->assertSessionHasErrors();
        $this->assertSame(1, SchedulePeriod::query()->count());
        $this->assertSame($existingPeriod->id, (int) SchedulePeriod::query()->firstOrFail()->id);
    }

    public function test_bulk_store_rejects_team_from_another_post(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $otherPost = $this->createPost($unit);
        $team = $this->createTeam($otherPost);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/schedule-period/bulk', [
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'name' => 'Bulk Period',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => SchedulePeriod::STATUS_DRAFT,
            'teams' => [
                [
                    'security_team_id' => $team->id,
                    'members' => [],
                ],
            ],
        ]);

        $response->assertSessionHasErrors();
        $this->assertSame(0, SchedulePeriod::query()->count());
    }

    public function test_bulk_store_rejects_inactive_team(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $team->update(['is_active' => false]);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/schedule-period/bulk', [
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'name' => 'Bulk Period',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => SchedulePeriod::STATUS_DRAFT,
            'teams' => [
                [
                    'security_team_id' => $team->id,
                    'members' => [],
                ],
            ],
        ]);

        $response->assertSessionHasErrors();
        $this->assertSame(0, SchedulePeriod::query()->count());
    }

    public function test_bulk_store_rejects_invalid_member(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unit);
        $otherUnit = $this->createUnit();
        $outsider = $this->createSecurity($otherUnit);
        $pattern = $this->createPattern($unit);

        $response = $this->actingAs($admin)->post('/admin/schedule-period/bulk', [
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'name' => 'Bulk Period',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => SchedulePeriod::STATUS_DRAFT,
            'teams' => [
                [
                    'security_team_id' => $team->id,
                    'members' => [
                        [
                            'security_id' => $outsider->id,
                            'schedule_pattern_id' => $pattern->id,
                            'start_offset' => 0,
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertSessionHasErrors();
        $this->assertSame(0, SchedulePeriod::query()->count());
    }

    public function test_bulk_store_rejects_unit_out_of_scope(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $postB = $this->createPost($unitB);
        $teamB = $this->createTeam($postB);
        $admin = $this->createAdmin($unitA);

        $response = $this->actingAs($admin)->post('/admin/schedule-period/bulk', [
            'unit_id' => $unitB->id,
            'security_post_id' => $postB->id,
            'name' => 'Bulk Period',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => SchedulePeriod::STATUS_DRAFT,
            'teams' => [
                [
                    'security_team_id' => $teamB->id,
                    'members' => [],
                ],
            ],
        ]);

        $response->assertSessionHasErrors('unit_id');
        $this->assertSame(0, SchedulePeriod::query()->count());
    }

    public function test_bulk_store_flashes_bulk_created_summary(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $alpha = $this->createTeam($post, 'A');
        $bravo = $this->createTeam($post, 'B');
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/schedule-period/bulk', [
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'name' => 'Periode Bulk November',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => SchedulePeriod::STATUS_ACTIVE,
            'teams' => [
                ['security_team_id' => $alpha->id, 'members' => []],
                ['security_team_id' => $bravo->id, 'members' => []],
            ],
        ]);

        $response->assertRedirect(route('admin.schedule-period.index'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', '2 periode jadwal berhasil dibuat.');

        $summary = session('bulk_created');

        $this->assertIsArray($summary);
        $this->assertCount(2, $summary);

        $periods = SchedulePeriod::query()->orderBy('id')->get();
        $this->assertCount(2, $periods);

        foreach ($periods as $period) {
            $item = collect($summary)->firstWhere('id', $period->id);

            $this->assertNotNull($item);
            $this->assertSame($period->name, $item['name']);
            $this->assertSame($period->securityTeam->name, $item['team_name']);
            $this->assertSame('2026-11-01', $item['start_date']);
            $this->assertSame('2026-11-30', $item['end_date']);
            $this->assertSame(SchedulePeriod::STATUS_ACTIVE, $item['status']);
            $this->assertSame('Aktif', $item['status_label']);
            $this->assertSame(
                route('admin.schedule-period.show', $period->id),
                $item['show_url']
            );
        }
    }

    public function test_bulk_created_summary_is_shared_with_index_page(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $alpha = $this->createTeam($post, 'A');
        $bravo = $this->createTeam($post, 'B');
        $admin = $this->createAdmin($unit);

        $this->actingAs($admin)->post('/admin/schedule-period/bulk', [
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'name' => 'Periode Bulk Desember',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-31',
            'status' => SchedulePeriod::STATUS_DRAFT,
            'teams' => [
                ['security_team_id' => $alpha->id, 'members' => []],
                ['security_team_id' => $bravo->id, 'members' => []],
            ],
        ])->assertRedirect(route('admin.schedule-period.index'));

        $periods = SchedulePeriod::query()->orderBy('id')->get();
        $this->assertCount(2, $periods);

        $response = $this->actingAs($admin)->get('/admin/schedule-period');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SchedulePeriod/Index')
            ->where('flash.success', '2 periode jadwal berhasil dibuat.')
            ->has('flash.bulk_created', 2)
            ->where('flash.bulk_created.0.id', $periods[0]->id)
            ->where('flash.bulk_created.0.name', 'Periode Bulk Desember')
            ->where('flash.bulk_created.0.team_name', 'Regu A')
            ->where('flash.bulk_created.0.start_date', '2026-12-01')
            ->where('flash.bulk_created.0.end_date', '2026-12-31')
            ->where('flash.bulk_created.0.status', SchedulePeriod::STATUS_DRAFT)
            ->where('flash.bulk_created.0.status_label', 'Draft')
            ->where(
                'flash.bulk_created.0.show_url',
                route('admin.schedule-period.show', $periods[0]->id)
            )
            ->where('flash.bulk_created.1.id', $periods[1]->id)
            ->where('flash.bulk_created.1.team_name', 'Regu B')
        );
    }

    public function test_single_create_still_works(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/schedule-period', [
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'security_team_id' => $team->id,
            'name' => 'Single Period',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => SchedulePeriod::STATUS_DRAFT,
            'members' => [],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame(1, SchedulePeriod::query()->count());
    }
}
