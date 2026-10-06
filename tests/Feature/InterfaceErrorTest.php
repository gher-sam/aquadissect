<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InterfaceErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_pages_offer_an_authenticated_or_guest_destination(): void
    {
        $this->get('/missing-interface-page')->assertNotFound()->assertSee('Page not found')->assertSee('Sign in');
        $this->actingAs(User::factory()->create(['status' => 'active']))
            ->get('/missing-interface-page')->assertNotFound()->assertSee('Return to dashboard');
    }

    public function test_forbidden_pages_explain_access_without_granting_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'program_head', 'status' => 'active']))
            ->get('/people-sections')->assertForbidden()->assertSee('This page is not available to your account')
            ->assertSee('Get help')->assertDontSee('id="rosterForm"', false);
    }

    public function test_server_errors_keep_diagnostics_private_and_session_errors_offer_recovery(): void
    {
        config(['app.debug' => false]);
        Route::get('/interface-error-test', fn () => throw new \RuntimeException('Private diagnostic detail'));
        Route::get('/interface-session-test', fn () => abort(419));
        $this->get('/interface-error-test')->assertStatus(500)->assertSee('We could not open this page')
            ->assertDontSee('Private diagnostic detail');
        $this->get('/interface-session-test')->assertStatus(419)->assertSee('Your session needs to be refreshed');
    }
}
