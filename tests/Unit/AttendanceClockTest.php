<?php

namespace Tests\Unit;

use App\Support\AttendanceClock;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceClockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-11 18:30:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_wib_datetimes_are_converted_to_utc_for_storage(): void
    {
        $this->assertSame(
            '2026-11-11 00:00:00',
            AttendanceClock::wibToUtc('2026-11-11 07:00:00')->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-11-11 08:00:00',
            AttendanceClock::wibToUtc('2026-11-11 15:00:00')->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-11-11 12:00:00',
            AttendanceClock::wibToUtc('2026-11-11 19:00:00')->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-11-12 00:00:00',
            AttendanceClock::wibToUtc('2026-11-12 07:00:00')->format('Y-m-d H:i:s')
        );
    }

    public function test_stored_utc_datetimes_are_converted_to_wib_for_display(): void
    {
        $this->assertSame(
            '2026-11-11 07:00:00',
            AttendanceClock::utcToWib('2026-11-11 00:00:00')->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '11 Nov 2026, 07:00',
            AttendanceClock::formatWib(Carbon::parse('2026-11-11 00:00:00', 'UTC'))
        );
        $this->assertSame(
            '2026-11-11T07:00',
            AttendanceClock::formatWibInput(Carbon::parse('2026-11-11 00:00:00', 'UTC'))
        );
    }

    public function test_shift_boundaries_are_built_in_wib_and_stored_in_utc(): void
    {
        $this->assertSame(
            '2026-11-11 00:00:00',
            AttendanceClock::scheduledStart('2026-11-11', '07:00:00')->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-11-11 08:00:00',
            AttendanceClock::scheduledEnd('2026-11-11', '15:00:00')->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-11-12 00:00:00',
            AttendanceClock::scheduledEnd('2026-11-11', '07:00:00', 1)->format('Y-m-d H:i:s')
        );
    }

    public function test_current_operational_date_uses_wib_across_the_utc_boundary(): void
    {
        $this->assertSame('2026-11-12', AttendanceClock::todayWib());
        $this->assertSame('2026-11-12 01:30:00', AttendanceClock::nowWib()->format('Y-m-d H:i:s'));
    }
}
