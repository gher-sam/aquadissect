<?php

namespace App\Providers;

use App\Services\AutomaticDatabaseSetup;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Http\Request;
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
        foreach ([\App\Models\CalendarEvent::class, \App\Models\SpeciesAssignment::class, \App\Models\ClassModuleAssignment::class,
            \App\Models\User::class, \App\Models\SpeciesPackage::class, \App\Models\LearningModuleVersion::class,
            \App\Models\SpeciesPackageReview::class, \App\Models\LearningModuleReview::class] as $model) {
            $model::observe(\App\Services\WorkspaceNotifications::class);
        }
        if (! $this->app->runningInConsole()) {
            $this->app->make(AutomaticDatabaseSetup::class)->ensureCurrent($this->app->make(Migrator::class));
        }

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(8)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);

        RateLimiter::for('registration', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
        ]);
    }
}
