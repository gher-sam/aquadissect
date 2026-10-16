<?php

use App\Services\RosterUploadDrafts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('clone:apply')
    ->everyMinute()
    ->withoutOverlapping(10);

Artisan::command('roster:expire-uploads', function () {
    $this->info(app(RosterUploadDrafts::class)->cleanup().' expired uploads removed.');
})->purpose('Remove private roster drafts after 30 minutes');

Schedule::command('roster:expire-uploads')->everyFiveMinutes()->withoutOverlapping();
