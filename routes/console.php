<?php

use App\Models\StudentSetupChallenge;
use App\Models\StudentSetupCode;
use App\Services\GuidedClipDrafts;
use App\Services\RosterUploadDrafts;
use App\Services\StudentEmailSetup;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
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

Artisan::command('clips:expire-drafts', function () {
    $this->info(app(GuidedClipDrafts::class)->cleanup().' expired clip drafts removed.');
})->purpose('Remove private guided-clip drafts after 24 hours');

Schedule::command('clips:expire-drafts')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('student-setup:status', function () {
    $setup = app(StudentEmailSetup::class);
    $this->line('Authenticated email delivery configured: '.($setup->mailConfigured() ? 'yes' : 'no'));
    $this->line('Inbox delivery confirmed: '.(config('student-setup.delivery_verified') ? 'yes' : 'no'));
    $this->line('Student email setup available: '.($setup->ready() ? 'yes' : 'no'));
})->purpose('Check student email setup readiness without exposing mail credentials');

Artisan::command('student-setup:test-email {recipient}', function () {
    if (! app(StudentEmailSetup::class)->mailConfigured() || ! filter_var($this->argument('recipient'), FILTER_VALIDATE_EMAIL)) {
        $this->error('Connect authenticated email delivery and supply a valid designated test recipient first.');

        return 1;
    }
    try {
        Mail::raw('This is the requested AquaDissect email delivery test. No student account was changed. Confirm receipt before enabling student email setup.', function ($message) {
            $message->to($this->argument('recipient'))->subject('AquaDissect email delivery test');
        });
        $this->info('The sending service accepted the test message. Confirm actual inbox receipt; setup remains disabled until explicitly enabled.');

        return 0;
    } catch (Throwable) {
        $this->error('The delivery test could not be sent. Check the private sending connection. No credentials have been printed.');

        return 1;
    }
})->purpose('Send a requested delivery test without enabling student setup');

Artisan::command('student-setup:prune', function () {
    $challenges = StudentSetupChallenge::where('expires_at', '<=', now())->delete();
    $codes = StudentSetupCode::where('expires_at', '<=', now())->delete();
    $this->info("Expired setup records removed: {$challenges} sessions, {$codes} codes.");
})->purpose('Remove expired private setup and verification state');
Schedule::command('student-setup:prune')->hourly()->withoutOverlapping();
