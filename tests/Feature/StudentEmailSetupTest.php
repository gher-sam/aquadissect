<?php

namespace Tests\Feature;

use App\Mail\StudentSetupOtp;
use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\RosterEvent;
use App\Models\StudentSetupChallenge;
use App\Models\StudentSetupCode;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class StudentEmailSetupTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->student = User::factory()->create(['first_name' => 'QA', 'last_name' => 'Learner', 'email' => null, 'email_verified_at' => null,
            'identity_number' => 'QA-OTP-001', 'role' => 'student', 'status' => 'pending']);
        config(['student-setup.enabled' => true, 'student-setup.delivery_verified' => true,
            'mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.username' => 'qa-sender',
            'mail.mailers.smtp.password' => 'synthetic-smtp-fixture', 'mail.from.address' => 'noreply@example.test']);
        Mail::fake();
    }

    public function test_successful_setup_retains_identity_enrollment_and_results_until_verified_completion(): void
    {
        $class = LearningClass::firstOrFail();
        ClassEnrollment::create(['class_id' => $class->id, 'user_id' => $this->student->id, 'status' => 'active']);
        $before = $this->student->getRawOriginal();
        $users = User::count();
        $code = $this->issue();
        $this->claim($code);
        $this->send();
        $otp = $this->otp();
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $otp);
        $this->assertNull($this->student->fresh()->email);
        $this->assertSame($before['password'], $this->student->fresh()->getRawOriginal('password'));
        $raw = DB::table('student_setup_challenges')->first();
        $this->assertStringNotContainsString('qa.otp@example.test', $raw->email);
        $this->assertNotSame($otp, $raw->otp_hash);
        $this->postJson('/api/student-setup/verify', ['otp' => $otp])->assertOk()->assertJsonPath('stage', 'password');
        $this->postJson('/api/student-setup/complete', ['password' => 'StrongSetup123!', 'password_confirmation' => 'StrongSetup123!'])->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $student = $this->student->fresh();
        $this->assertSame('qa.otp@example.test', $student->email);
        $this->assertSame('active', $student->status);
        $this->assertNotNull($student->email_verified_at);
        $this->assertTrue(Hash::check('StrongSetup123!', $student->password));
        foreach (['id', 'identity_number', 'first_name', 'last_name', 'role'] as $field) {
            $this->assertSame($before[$field], $student->getRawOriginal($field));
        }
        $this->assertSame($users, User::count());
        $this->assertDatabaseHas('class_enrollments', ['class_id' => $class->id, 'user_id' => $student->id, 'status' => 'active']);
        $this->assertDatabaseCount('student_setup_challenges', 0);
        $this->assertNotNull(StudentSetupCode::where('user_id', $student->id)->first()->consumed_at);
        $this->assertDatabaseHas('clone_outbox', ['table_name' => 'users', 'record_key' => (string) $student->id, 'operation' => 'update']);
        $this->assertDatabaseMissing('clone_outbox', ['table_name' => 'student_setup_codes']);
        $this->assertDatabaseMissing('clone_outbox', ['table_name' => 'student_setup_challenges']);
        $this->postJson('/api/student-setup/claim', ['identityNumber' => $student->identity_number, 'setupCode' => $code])->assertUnprocessable();
        $this->postJson('/api/login', ['email' => $student->email, 'password' => 'StrongSetup123!'])->assertOk();
        $this->getJson('/api/species')->assertOk()->assertJsonCount(0, 'species');
    }

    public function test_code_issuance_is_administrator_only_atomic_and_never_publishes_secrets_in_audit(): void
    {
        $data = ['userIds' => [$this->student->id]];
        $this->postJson('/api/admin/student-setup-codes', $data)->assertUnauthorized();
        foreach (['student', 'instructor', 'program_head'] as $role) {
            $actor = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($actor)->postJson('/api/admin/student-setup-codes', $data)->assertForbidden();
        }
        $this->actingAs($this->admin)->postJson('/api/admin/student-setup-codes', ['userIds' => [$this->student->id, $this->admin->id]])->assertUnprocessable();
        $this->assertDatabaseCount('student_setup_codes', 0);
        $this->postJson('/api/admin/student-setup-codes', ['userIds' => [$this->student->id, $this->student->id]])->assertUnprocessable();
        $code = $this->issue();
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{4}(-[A-HJ-NP-Z2-9]{4}){2}$/', $code);
        $record = StudentSetupCode::where('user_id', $this->student->id)->first();
        $this->assertSame(7, (int) now()->startOfSecond()->diffInDays($record->expires_at));
        $this->assertNotSame(str_replace('-', '', $code), $record->code_hash);
        $this->assertStringNotContainsString($code, RosterEvent::get()->toJson());
        $this->assertStringNotContainsString($record->code_hash, $record->toJson());
    }

    public function test_setup_is_unavailable_until_enabled_and_delivery_is_confirmed_with_real_mail_transport(): void
    {
        foreach ([['student-setup.enabled' => false], ['student-setup.delivery_verified' => false], ['mail.default' => 'log'], ['mail.mailers.smtp.password' => null]] as $settings) {
            $original = collect($settings)->mapWithKeys(fn ($v, $key) => [$key => config($key)])->all();
            config($settings);
            $this->getJson('/api/student-setup/status')->assertOk()->assertJsonPath('enabled', false);
            $this->postJson('/api/student-setup/claim', ['identityNumber' => $this->student->identity_number, 'setupCode' => 'WRONG'])->assertStatus(503);
            config($original);
        }
        Mail::assertNothingSent();
        $this->assertSame('pending', $this->student->fresh()->status);
    }

    public function test_invalid_expired_and_replaced_setup_codes_are_rejected_without_account_changes(): void
    {
        $first = $this->issue();
        $second = $this->issue();
        foreach ([['identityNumber' => 'UNKNOWN', 'setupCode' => $second], ['identityNumber' => $this->student->identity_number, 'setupCode' => $first]] as $payload) {
            $this->postJson('/api/student-setup/claim', $payload)->assertUnprocessable();
        }
        StudentSetupCode::query()->update(['expires_at' => now()->subSecond()]);
        $this->postJson('/api/student-setup/claim', ['identityNumber' => $this->student->identity_number, 'setupCode' => $second])->assertUnprocessable();
        $this->assertNull($this->student->fresh()->email);
        $this->assertDatabaseCount('student_setup_challenges', 0);
    }

    public function test_verification_and_completion_are_bound_to_the_claimed_browser_session(): void
    {
        $this->claim($this->issue());
        $this->send();
        $original = session('student_setup');
        $this->withSession(['student_setup' => [...$original, 'nonce' => 'another-browser']])->postJson('/api/student-setup/verify', ['otp' => $this->otp()])->assertUnprocessable();
        $this->postJson('/api/student-setup/cancel')->assertOk();
        $this->assertDatabaseCount('student_setup_challenges', 1);
        $this->withSession(['student_setup' => $original])->postJson('/api/student-setup/verify', ['otp' => $this->otp()])->assertOk();
        $this->assertNull($this->student->fresh()->email);
    }

    public function test_wrong_code_attempts_are_committed_and_the_fifth_failure_blocks_even_the_correct_code(): void
    {
        $this->claim($this->issue());
        $this->send();
        $otp = $this->otp();
        $wrong = $otp === '000000' ? '000001' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/student-setup/verify', ['otp' => $wrong])->assertUnprocessable();
        }
        $this->assertSame(5, StudentSetupChallenge::first()->otp_attempts);
        $this->postJson('/api/student-setup/verify', ['otp' => $otp])->assertUnprocessable();
        $this->assertNull($this->student->fresh()->email);
    }

    public function test_resend_cooldown_replacement_and_hourly_limit_survive_reclaim(): void
    {
        $code = $this->issue();
        $this->claim($code);
        $this->send();
        $old = $this->otp();
        $this->postJson('/api/student-setup/email', ['email' => 'qa.otp@example.test'])->assertUnprocessable();
        $this->travel(61)->seconds();
        $this->send();
        $this->postJson('/api/student-setup/verify', ['otp' => $old])->assertUnprocessable();
        for ($i = 0; $i < 3; $i++) {
            $this->travel(61)->seconds();
            $this->send();
        }
        $this->claim($code);
        $this->travel(61)->seconds();
        $this->postJson('/api/student-setup/email', ['email' => 'qa.otp@example.test'])->assertUnprocessable();
        Mail::assertSent(StudentSetupOtp::class, 5);
        $this->travel(1)->hours();
        $this->claim($code);
        $this->send();
        Mail::assertSent(StudentSetupOtp::class, 6);
    }

    public function test_expired_otp_and_expired_verified_grant_do_not_activate_an_account(): void
    {
        $code = $this->issue();
        $this->claim($code);
        $this->send();
        $otp = $this->otp();
        $this->travel(11)->minutes();
        $this->postJson('/api/student-setup/verify', ['otp' => $otp])->assertUnprocessable();
        $this->send();
        $this->postJson('/api/student-setup/verify', ['otp' => $this->otp()])->assertOk();
        $this->travel(6)->minutes();
        $this->postJson('/api/student-setup/complete', ['password' => 'StrongSetup123!', 'password_confirmation' => 'StrongSetup123!'])->assertUnprocessable();
        $this->assertSame('pending', $this->student->fresh()->status);
    }

    public function test_email_change_requires_new_verification_and_duplicate_email_is_rejected(): void
    {
        $this->claim($this->issue());
        $this->send();
        $this->postJson('/api/student-setup/verify', ['otp' => $this->otp()])->assertOk();
        $this->travel(61)->seconds();
        $this->send('changed@example.test');
        $this->postJson('/api/student-setup/complete', ['password' => 'StrongSetup123!', 'password_confirmation' => 'StrongSetup123!'])->assertUnprocessable();
        $this->travel(61)->seconds();
        $this->postJson('/api/student-setup/email', ['email' => strtoupper($this->admin->email)])->assertUnprocessable();
        $this->assertNull($this->student->fresh()->email);
    }

    public function test_failed_email_send_invalidates_verification_and_does_not_expose_provider_diagnostics(): void
    {
        $this->claim($this->issue());
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('private SMTP diagnostic fixture'));
        $this->postJson('/api/student-setup/email', ['email' => 'qa.otp@example.test'])->assertUnprocessable()->assertDontSee('private SMTP diagnostic fixture');
        $challenge = StudentSetupChallenge::first();
        $this->assertSame('failed', $challenge->delivery_state);
        $this->assertNull($challenge->otp_hash);
        $this->assertNull($this->student->fresh()->email);
        $this->assertSame('pending', $this->student->fresh()->status);
    }

    public function test_cancellation_keeps_the_account_and_setup_code_available_without_preserving_a_verified_session(): void
    {
        $code = $this->issue();
        $this->claim($code);
        $this->send();
        $this->postJson('/api/student-setup/verify', ['otp' => $this->otp()])->assertOk();
        $this->postJson('/api/student-setup/cancel')->assertOk();
        $this->assertDatabaseCount('student_setup_challenges', 0);
        $this->assertNull(StudentSetupCode::first()->consumed_at);
        $this->assertNull($this->student->fresh()->email);
        $this->postJson('/api/student-setup/complete', ['password' => 'StrongSetup123!', 'password_confirmation' => 'StrongSetup123!'])->assertUnprocessable();
        $this->claim($code);
    }

    public function test_completion_rechecks_concurrent_account_and_email_changes(): void
    {
        $this->claim($this->issue());
        $this->send();
        $this->postJson('/api/student-setup/verify', ['otp' => $this->otp()])->assertOk();
        $other = User::factory()->create(['email' => 'qa.otp@example.test']);
        $payload = ['password' => 'StrongSetup123!', 'password_confirmation' => 'StrongSetup123!'];
        $this->postJson('/api/student-setup/complete', $payload)->assertUnprocessable();
        $this->student->update(['email' => 'administrator-added@example.test']);
        $this->postJson('/api/student-setup/complete', $payload)->assertUnprocessable();
        $this->assertSame('administrator-added@example.test', $this->student->fresh()->email);
        $this->assertSame('pending', $this->student->fresh()->status);
    }

    public function test_password_or_activation_changes_during_setup_invalidate_the_verified_session(): void
    {
        foreach ([['password' => 'AdministratorReset456!'], ['status' => 'active']] as $change) {
            $this->student = User::factory()->create(['email' => null, 'email_verified_at' => null, 'role' => 'student', 'status' => 'pending']);
            $this->claim($this->issue());
            $this->send();
            $this->postJson('/api/student-setup/verify', ['otp' => $this->otp()])->assertOk();
            $this->student->update($change);
            $password = $this->student->fresh()->getRawOriginal('password');
            $this->postJson('/api/student-setup/complete', ['password' => 'StrongSetup123!', 'password_confirmation' => 'StrongSetup123!'])->assertUnprocessable();
            $this->assertSame($password, $this->student->fresh()->getRawOriginal('password'));
            $this->assertNull($this->student->fresh()->email);
            $this->assertNull(StudentSetupCode::where('user_id', $this->student->id)->first()->consumed_at);
        }
    }

    private function issue(): string
    {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/student-setup-codes', ['userIds' => [$this->student->id]])->assertOk();
        auth()->logout();

        return $response->json('codes.0.code');
    }

    private function claim(string $code): void
    {
        $this->postJson('/api/student-setup/claim', ['identityNumber' => $this->student->identity_number, 'setupCode' => $code])->assertOk()->assertJsonPath('stage', 'email');
    }

    private function send(string $email = 'qa.otp@example.test'): void
    {
        $this->postJson('/api/student-setup/email', ['email' => $email])->assertOk()->assertJsonPath('stage', 'otp');
    }

    private function otp(): string
    {
        return Mail::sent(StudentSetupOtp::class)->last()->otp;
    }
}
