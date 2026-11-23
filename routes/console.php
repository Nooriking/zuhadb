<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:backup-database --keep='.(int) config('business.backup_keep', 14))
    ->dailyAt('02:00')
    ->name('daily-database-backup')
    ->withoutOverlapping();

