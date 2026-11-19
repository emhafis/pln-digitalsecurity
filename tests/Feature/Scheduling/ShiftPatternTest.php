<?php

namespace Tests\Feature\Scheduling;

use App\Models\SchedulePatternDetail;
use Illuminate\Validation\ValidationException;

class ShiftPatternTest extends SchedulingTestCase
{
    public function test_shift_belongs_to_unit_and_unit_has_many_shifts(): void
    {
        $unit = $this->createUnit();
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');

        $this->assertTrue($shift->unit->is($unit));
        $this->assertTrue($unit->shifts->contains($shift));
    }

    public function test_night_shift_supports_next_day_offset(): void
    {
        $unit = $this->createUnit();
        $shift = $this->createShift($unit, 'Malam', 'MALAM', [
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'end_day_offset' => 1,
        ]);

        $this->assertSame(1, $shift->end_day_offset);
    }

    public function test_schedule_pattern_has_ordered_details_with_shift_relation(): void
    {
        $unit = $this->createUnit();
        $pattern = $this->createPattern($unit);
        $pagi = $this->createShift($unit, 'Pagi', 'PAGI');
        $siang = $this->createShift($unit, 'Siang', 'SIANG');

        $pattern->addDetail($siang, 2);
        $pattern->addDetail($pagi, 1);

        $details = $pattern->fresh()->details;

        $this->assertSame([1, 2], $details->pluck('day_order')->all());
        $this->assertTrue($details[0]->shift->is($pagi));
        $this->assertTrue($details[1]->shift->is($siang));
    }

    public function test_pattern_add_detail_rejects_shift_from_another_unit(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $pattern = $this->createPattern($unitA);
        $foreignShift = $this->createShift($unitB, 'Pagi B', 'PAGIB');

        $this->expectException(ValidationException::class);

        $pattern->addDetail($foreignShift, 1);
    }

    public function test_pattern_detail_model_rejects_cross_unit_shift(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $pattern = $this->createPattern($unitA);
        $foreignShift = $this->createShift($unitB, 'Malam B', 'MALAMB');

        $this->expectException(ValidationException::class);

        SchedulePatternDetail::create([
            'schedule_pattern_id' => $pattern->id,
            'shift_id' => $foreignShift->id,
            'day_order' => 1,
        ]);
    }
}
