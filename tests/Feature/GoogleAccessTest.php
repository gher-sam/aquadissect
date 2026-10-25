<?php

namespace Tests\Feature;

use App\Models\AccountActivationToken;
use App\Models\ExternalIdentity;
use App\Models\LessonResult;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleAccessTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.enabled' => true, 'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret', 'services.google.redirect' => 'http://localhost/auth/google/callback']);
    }

    private function profile(string $email, string $subject = 'verified-subject', bool $verified = true): void
    {
        $profile = (new GoogleUser)->setRaw(['email_verified' => $verified, 'given_name' => 'Faculty', 'family_name' => 'Reviewer'])
            ->map(['id' => $subject, 'email' => $email]);
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('setHttpClient')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($profile);
        $factory = Mockery::mock(Factory::class);
        $factory->shouldReceive('driver')->with('google')->andReturn($provider);
        Socialite::swap($factory);
    }

    private function completeGoogleSignIn(string $intent = 'signin')
    {
        return $this->withSession(['google_intent' => ['action' => $intent, 'expires' => now()->addMinutes(10)->timestamp]])
            ->get('/auth/google/callback?code=test-code');
    }

    public function test_every_active_role_can_link_without_changing_its_password_or_role(): void
    {
        foreach (['student', 'instructor', 'program_head', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active', 'email' => $role.'@example.test']);
            $hash = $user->password;
            $this->profile(strtoupper($user->email), $role.'-subject');
            $this->completeGoogleSignIn()->assertRedirect('/dashboard');
            $this->assertAuthenticatedAs($user);
            $this->assertSame($hash, $user->fresh()->password);
            $this->assertSame($role, $user->fresh()->role);
            $this->assertDatabaseHas('external_identities', ['user_id' => $user->id, 'subject' => $role.'-subject']);
            $this->postJson('/api/logout')->assertOk();
        }
    }

    public function test_rostered_student_is_activated_and_old_activation_token_removed(): void
    {
        $user = User::factory()->create(['role' => 'student', 'status' => 'pending', 'email' => 'learner@example.test']);
        AccountActivationToken::create(['user_id' => $user->id, 'token_hash' => hash('sha256', 'code'), 'expires_at' => now()->addDay()]);
        $this->profile($user->email);
        $this->completeGoogleSignIn('activate')->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('active', $user->fresh()->status);
        $this->assertNull($user->fresh()->password);
        $this->assertSame($user->first_name, $user->fresh()->first_name);
        $this->assertDatabaseMissing('account_activation_tokens', ['user_id' => $user->id]);
        $this->assertDatabaseHas('roster_events', ['user_id' => $user->id, 'action' => 'student_activated']);
    }

    public function test_google_sign_in_cannot_bypass_a_required_student_password_change(): void
    {
        foreach (['active', 'pending'] as $status) {
            $student = User::factory()->create(['role' => 'student', 'status' => $status, 'identity_number' => 'QA-GOOGLE-'.strtoupper($status),
                'password' => 'QA-GOOGLE-RESET', 'must_change_password' => true, 'password_revision' => 1]);
            $this->profile($student->email, 'qa-reset-'.$status);
            $this->completeGoogleSignIn()->assertRedirect('/dashboard');
            $this->assertSame($status, $student->fresh()->status);
            $this->assertSame($student->password, $student->fresh()->password);
            $this->get('/dashboard')->assertRedirect('/change-password');
            $this->getJson('/api/classes')->assertForbidden()->assertJsonPath('passwordChangeRequired', true);
            $this->postJson('/api/change-password', ['password' => 'GooglePrivate123!', 'password_confirmation' => 'GooglePrivate123!'])->assertOk();
            $this->assertFalse($student->fresh()->must_change_password);
            $this->assertSame('active', $student->fresh()->status);
            $this->assertDatabaseHas('external_identities', ['user_id' => $student->id]);
            $this->postJson('/api/logout')->assertOk();
        }
    }

    public function test_pending_instructor_remains_pending(): void
    {
        $user = User::factory()->create(['role' => 'instructor', 'status' => 'pending']);
        $this->profile($user->email);
        $this->completeGoogleSignIn()->assertRedirect('/login')->assertSessionHas('google_message');
        $this->assertGuest();
        $this->assertSame('pending', $user->fresh()->status);
    }

    public function test_unmatched_student_cannot_self_register(): void
    {
        $this->profile('unrostered@example.test');
        $this->completeGoogleSignIn('activate')->assertRedirect('/login');
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_instructor_request_uses_only_verified_claim_and_requires_approval(): void
    {
        $this->profile('faculty@example.test');
        $this->completeGoogleSignIn('register')->assertRedirect('/instructor-registration');
        $payload = ['firstName' => 'Faculty', 'lastName' => 'Reviewer', 'identityNumber' => 'EMP-TEST',
            'agreement' => true, 'role' => 'admin', 'email' => 'spoofed@example.test', 'password' => 'Password123'];
        $this->postJson('/api/register/google', $payload)->assertCreated()->assertSessionMissing('google_registration');
        $user = User::firstOrFail();
        $this->assertSame('faculty@example.test', $user->email);
        $this->assertSame('instructor', $user->role);
        $this->assertSame('pending', $user->status);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertGuest();
        $this->postJson('/api/register/google', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_unverified_email_and_collision_are_rejected(): void
    {
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $this->profile($user->email, 'subject', false);
        $this->completeGoogleSignIn()->assertRedirect('/login');
        $this->assertDatabaseCount('external_identities', 0);
        $this->profile($user->email);
        ExternalIdentity::create(['user_id' => $user->id, 'provider' => 'google', 'subject' => 'other', 'verified_email' => $user->email, 'verified_at' => now()]);
        $this->completeGoogleSignIn()->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseCount('external_identities', 1);
    }

    public function test_linked_subject_survives_google_email_change_without_changing_account_email(): void
    {
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);
        ExternalIdentity::create(['user_id' => $user->id, 'provider' => 'google', 'subject' => 'same-subject', 'verified_email' => $user->email, 'verified_at' => now()]);
        $this->profile('changed@example.test', 'same-subject');
        $this->completeGoogleSignIn()->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_state_expiry_cancellation_and_replay_cannot_authenticate(): void
    {
        $this->get('/auth/google/callback')->assertRedirect('/login');
        $this->withSession(['google_intent' => ['action' => 'signin', 'expires' => now()->subMinute()->timestamp]])
            ->get('/auth/google/callback')->assertRedirect('/login');
        // Real stateful provider rejects a callback with no matching session state, before any network request.
        $this->completeGoogleSignIn()->assertRedirect('/login');
        $this->get('/auth/google/callback?code=test-code')->assertRedirect('/login');
        $this->completeGoogleSignIn('register')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseCount('external_identities', 0);
    }

    public function test_google_redirect_uses_state_and_minimum_scopes(): void
    {
        $response = $this->get('/auth/google/signin')->assertRedirect()->assertHeader('Cache-Control', 'no-store, private');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertNotEmpty($query['state']);
        $this->assertSame('select_account', $query['prompt']);
        $this->assertSame(['openid', 'email', 'profile'], explode(' ', $query['scope']));
        $this->assertSame($query['state'], session('state'));
    }

    public function test_manual_registration_is_blocked_when_google_is_required_and_ui_matches(): void
    {
        $this->postJson('/api/register', ['firstName' => 'Faculty', 'lastName' => 'Reviewer', 'email' => 'manual@example.test',
            'identityNumber' => 'EMP-MANUAL', 'role' => 'instructor', 'password' => 'Password123', 'agreement' => true])->assertUnprocessable();
        $this->get('/instructor-registration')->assertOk()->assertSee('Step 1 of 2')->assertDontSee('id="registrationPassword"', false);
        $this->get('/login')->assertOk()->assertSee('Continue with Google');
        config(['services.google.enabled' => false]);
        $this->get('/auth/google/signin')->assertStatus(503);
        $this->get('/login')->assertDontSee('Continue with Google');
        $this->get('/instructor-registration')->assertSee('id="registrationPassword"', false);
    }

    public function test_claim_is_account_scoped_expiring_and_requires_agreement(): void
    {
        $payload = ['firstName' => 'Faculty', 'lastName' => 'Reviewer', 'identityNumber' => 'EMP-CLAIM'];
        $this->postJson('/api/register/google', $payload)->assertUnprocessable();
        $this->withSession(['google_registration' => ['expires' => now()->subMinute()->timestamp]])->postJson('/api/register/google', $payload)->assertUnprocessable();
        $this->withSession(['google_registration' => ['subject' => 'claim', 'email' => 'claim@example.test', 'expires' => now()->addMinutes(10)->timestamp]])
            ->postJson('/api/register/google', $payload)->assertUnprocessable()->assertJsonValidationErrors('agreement');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_provider_failure_cancellation_and_rate_limit_have_no_account_side_effects(): void
    {
        $this->withSession(['google_intent' => ['action' => 'signin', 'expires' => now()->addMinutes(10)->timestamp], 'state' => 'cancel-state'])
            ->get('/auth/google/callback?error=access_denied')->assertRedirect('/login')->assertSessionMissing('state');
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('setHttpClient')->andReturnSelf();
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('Provider failure'));
        $factory = Mockery::mock(Factory::class);
        $factory->shouldReceive('driver')->andReturn($provider);
        Socialite::swap($factory);
        $this->completeGoogleSignIn()->assertRedirect('/login')->assertSessionHas('google_message');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('external_identities', 0);
        for ($i = 0; $i < 4; $i++) {
            $this->get('/auth/google/callback');
        }
        $this->get('/auth/google/callback')->assertStatus(429);
        $this->assertGuest();
    }

    public function test_google_activation_preserves_enrollment_assignments_and_lesson_results(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('role', 'student')->firstOrFail();
        $user->update(['status' => 'pending']);
        $counts = collect(['class_enrollments', 'class_instructors', 'species_assignments', 'lesson_results'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
        $id = $user->id;
        $this->profile($user->email);
        $this->completeGoogleSignIn('activate')->assertRedirect('/dashboard');
        $this->assertSame($id, auth()->id());
        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertDatabaseHas('class_enrollments', ['user_id' => $id]);
    }

    public function test_claim_details_render_without_password_and_expired_claim_returns_to_google(): void
    {
        $claim = ['subject' => 'render-subject', 'email' => 'render@example.test', 'first_name' => 'Faculty', 'last_name' => 'Reviewer', 'expires' => now()->addMinutes(10)->timestamp];
        $this->withSession(['google_registration' => $claim])->get('/instructor-registration')->assertOk()
            ->assertSee('Step 2 of 2')->assertSee('render@example.test')->assertSee('readonly', false)->assertDontSee('id="registrationPassword"', false);
        $this->withSession(['google_registration' => array_replace($claim, ['expires' => now()->subMinute()->timestamp])])
            ->get('/instructor-registration')->assertSee('Step 1 of 2')->assertDontSee('render@example.test');
    }

    public function test_google_only_account_has_no_password_fallback_and_disabled_feature_retains_existing_passwords(): void
    {
        $user = User::factory()->create(['role' => 'student', 'status' => 'active', 'password' => null]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Password123'])->assertUnprocessable();
        $this->assertGuest();
        config(['services.google.enabled' => false]);
        $existing = User::factory()->create(['role' => 'student', 'status' => 'active', 'password' => 'Password123']);
        $this->postJson('/api/login', ['email' => $existing->email, 'password' => 'Password123'])->assertOk();
        $this->assertAuthenticatedAs($existing);
        $this->postJson('/api/register/google', [])->assertForbidden();
    }

    public function test_additive_migration_preserves_existing_accounts_assignments_and_historical_results(): void
    {
        Schema::table('users', fn ($table) => $table->string('password')->nullable(false)->change());
        $this->seed(DatabaseSeeder::class);
        $student = User::where('role', 'student')->firstOrFail();
        LessonResult::create(['user_id' => $student->id, 'lesson_key' => 'tilapia-guided-dissection',
            'score' => 2, 'total' => 3, 'completed_at' => now(), 'submitted_at' => now(), 'synced_at' => now()]);
        $tables = ['users', 'classes', 'class_enrollments', 'class_instructors', 'species_assignments', 'lesson_results'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        Schema::drop('external_identities');
        $migration = require database_path('migrations/2026_10_02_000001_add_google_external_identities.php');
        $migration->up();
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->orderBy('id')->get()->toJson());
        }
        $this->assertDatabaseCount('external_identities', 0);
    }
}
