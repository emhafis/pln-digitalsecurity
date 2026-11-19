<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('attendance:generate')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('attendance:evaluate-absent')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('patrol:generate-sessions')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
