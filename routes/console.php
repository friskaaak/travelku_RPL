<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lepas hold yang kedaluwarsa setiap menit. Kebenaran tidak bergantung pada ini
// (hold juga dikedaluwarsakan secara lazy), tetapi jadwal ini menjaga data tetap bersih.
Schedule::command('holds:expire')->everyMinute()->withoutOverlapping();