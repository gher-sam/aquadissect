<?php

namespace App\Providers;

use App\Mail\GmailApiTransport;
use App\Models\CalendarEvent;
use App\Models\ClassModuleAssignment;
use App\Models\LearningModuleReview;
use App\Models\LearningModuleVersion;
use App\Models\SpeciesAssignment;
use App\Models\SpeciesPackage;
use App\Models\SpeciesPackageReview;
use App\Models\User;
use App\Services\AutomaticDatabaseSetup;
use App\Services\GmailSender;
use App\Services\WorkspaceNotifications;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('gmail', fn () => new GmailApiTransport(app(GmailSender::class)));
        foreach ([CalendarEvent::class, SpeciesAssignment::class, ClassModuleAssignment::class,
            User::class, SpeciesPackage::class, LearningModuleVersion::class,
            SpeciesPackageReview::class, LearningModuleReview::class] as $model) {
            $model::observe(WorkspaceNotifications::class);
        }
        if (! $this->app->runningInConsole()) {
            $this->app->make(AutomaticDatabaseSetup::class)->ensureCurrent($this->app->make(Migrator::class));
        }

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(8)->by(strtolower(trim((string) $request->input('identifier', $request->input('email')))).'|'.$request->ip()),
        ]);

        RateLimiter::for('registration', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
        ]);
        RateLimiter::for('student-password-change', fn (Request $request) => Limit::perMinute(10)->by('password-change|'.$request->user()?->id.'|'.$request->ip()));
        RateLimiter::for('student-setup', fn (Request $request) => Limit::perMinute(120)->by('student-setup|'.$request->ip()));
        RateLimiter::for('student-setup-claim', fn (Request $request) => Limit::perMinutes(10, 5)
            ->by('student-claim|'.hash('sha256', strtoupper(trim((string) $request->input('identityNumber')))).'|'.$request->ip()));
    }
}
