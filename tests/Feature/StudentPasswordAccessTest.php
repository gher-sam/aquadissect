<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\LessonResult;
use App\Models\StudentSetupChallenge;
use App\Models\StudentSetupCode;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StudentPasswordAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->student = User::factory()->create(['first_name' => 'QA', 'last_name' => 'Learner', 'identity_number' => 'QA-ID-ONLY',
            'email' => null, 'email_verified_at' => null, 'role' => 'student', 'status' => 'pending']);
        config(['student-setup.password_enabled' => true, 'student-setup.enabled' => false, 'student-setup.delivery_verified' => false, 'mail.default' => 'log']);
        Mail::fake();
    }

    public function test_student_can_activate_and_sign_in_without_email_while_retaining_records(): void
    {
        $class = LearningClass::firstOrFail();
        ClassEnrollment::create(['class_id' => $class->id, 'user_id' => $this->student->id, 'status' => 'active']);
        $result = LessonResult::create(['user_id' => $this->student->id, 'lesson_key' => 'qa-retained', 'score' => 2, 'total' => 5, 'completed_at' => now()]);
        $original = $this->student->getRawOriginal();
        $count = User::count();
        $code = $this->claim();
        $this->assertSame($original['password'], $this->student->fresh()->getRawOriginal('password'));
        $this->getJson('/api/student-password-setup/status')->assertOk()->assertJsonPath('stage', 'password');
        $this->complete()->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $student = $this->student->fresh();
        $this->assertSame('active', $student->status);
        $this->assertTrue(Hash::check('PrivatePassword123!', $student->password));
        $this->assertNull($student->email);
        $this->assertNull($student->email_verified_at);
        $this->assertSame($original['id'], $student->id);
        $this->assertSame($original['identity_number'], $student->identity_number);
        $this->assertSame($count, User::count());
        $this->assertDatabaseHas('class_enrollments', ['user_id' => $student->id, 'class_id' => $class->id, 'status' => 'active']);
        $this->assertDatabaseHas('lesson_results', ['id' => $result->id, 'score' => 2, 'total' => 5]);
        $this->assertNotNull(StudentSetupCode::where('user_id', $student->id)->first()->consumed_at);
        $this->assertDatabaseCount('student_setup_challenges', 0);
        $this->postJson('/api/student-password-setup/claim', ['identityNumber' => $student->identity_number, 'setupCode' => $code])->assertUnprocessable();
        $this->postJson('/api/login', ['identifier' => ' qa-id-only ', 'password' => 'PrivatePassword123!'])->assertOk()->assertJsonPath('user.id', $student->id);
        $this->getJson('/api/classes')->assertOk()->assertJsonPath('classes.0.id', $class->id);
        $this->getJson('/api/species')->assertOk()->assertJsonCount(0, 'species');
        Mail::assertNothingSent();
    }

    public function test_student_id_login_keeps_existing_email_password_access_and_rejects_staff_ids(): void
    {
        $this->student->update(['status' => 'active', 'password' => 'ExistingPassword123!']);
        $this->postJson('/api/login', ['identifier' => 'QA-ID-ONLY', 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/login', ['identifier' => 'UNKNOWN', 'password' => 'ExistingPassword123!'])->assertUnprocessable();
        $this->postJson('/api/login', ['identifier' => $this->admin->identity_number, 'password' => 'demo123'])->assertUnprocessable();
        $this->postJson('/api/login', ['identifier' => 'QA-ID-ONLY', 'password' => 'ExistingPassword123!'])->assertOk();
        $this->postJson('/api/logout')->assertOk();
        $this->postJson('/api/login', ['email' => $this->admin->email, 'password' => 'demo123'])->assertOk();
    }

    public function test_pending_accounts_and_accounts_without_password_cannot_skip_activation(): void
    {
        $this->student->update(['password' => 'ExistingPassword123!']);
        $this->postJson('/api/login', ['identifier' => $this->student->identity_number, 'password' => 'ExistingPassword123!'])->assertUnprocessable();
        $this->assertGuest();
        $this->student->update(['status' => 'active', 'password' => null]);
        $this->postJson('/api/login', ['identifier' => $this->student->identity_number, 'password' => 'anything'])->assertUnprocessable();
    }

    public function test_password_setup_can_be_disabled_independently_of_the_email_feature(): void
    {
        config(['student-setup.password_enabled' => false]);
        $this->getJson('/api/student-password-setup/status')->assertOk()->assertJsonPath('enabled', false);
        $this->postJson('/api/student-password-setup/claim', ['identityNumber' => 'QA-ID-ONLY', 'setupCode' => 'invalid'])->assertStatus(503);
        $this->complete()->assertStatus(503);
        $this->assertSame('pending', $this->student->fresh()->status);
    }

    public function test_invalid_expired_and_replaced_codes_do_not_activate_an_account(): void
    {
        $old = $this->claim();
        $new = $this->issue();
        $this->complete()->assertUnprocessable();
        foreach ([$old, 'INVALID'] as $code) {
            $this->postJson('/api/student-password-setup/claim', ['identityNumber' => 'QA-ID-ONLY', 'setupCode' => $code])->assertUnprocessable();
        }
        StudentSetupCode::where('user_id', $this->student->id)->update(['expires_at' => now()->subSecond()]);
        $this->postJson('/api/student-password-setup/claim', ['identityNumber' => 'QA-ID-ONLY', 'setupCode' => $new])->assertUnprocessable();
        $this->assertSame('pending', $this->student->fresh()->status);
    }

    public function test_password_setup_is_bound_to_the_browser_and_can_be_cancelled_or_expire(): void
    {
        $code = $this->claim();
        $session = session('student_setup');
        $this->withSession(['student_setup' => [...$session, 'nonce' => 'other-browser']]);
        $this->complete()->assertUnprocessable();
        $this->withSession(['student_setup' => $session])->postJson('/api/student-password-setup/cancel')->assertOk();
        $this->assertDatabaseCount('student_setup_challenges', 0);
        $this->complete()->assertUnprocessable();
        $this->postJson('/api/student-password-setup/claim', ['identityNumber' => 'QA-ID-ONLY', 'setupCode' => $code])->assertOk();
        $this->travel(31)->minutes();
        $this->complete()->assertUnprocessable();
        $this->assertSame('pending', $this->student->fresh()->status);
    }

    public function test_email_and_password_only_sessions_cannot_be_used_interchangeably(): void
    {
        $this->claim();
        config(['student-setup.enabled' => true, 'student-setup.delivery_verified' => true, 'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.username' => 'fixture',
            'mail.mailers.smtp.password' => 'synthetic-fixture', 'mail.from.address' => 'sender@example.test']);
        $payload = ['password' => 'PrivatePassword123!', 'password_confirmation' => 'PrivatePassword123!'];
        $this->postJson('/api/student-setup/email', ['email' => 'synthetic@example.test'])->assertUnprocessable();
        $this->postJson('/api/student-setup/complete', $payload)->assertUnprocessable();
        StudentSetupChallenge::where('user_id', $this->student->id)->update(['method' => 'email']);
        $this->complete()->assertUnprocessable();
        $this->assertSame('pending', $this->student->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_an_administrator_change_during_setup_preserves_the_new_credentials(): void
    {
        $this->claim();
        $this->student->update(['password' => 'AdministratorChange456!']);
        $this->complete()->assertUnprocessable();
        $this->assertTrue(Hash::check('AdministratorChange456!', $this->student->fresh()->password));
        $this->assertSame('pending', $this->student->fresh()->status);
    }

    public function test_weak_or_unconfirmed_password_does_not_consume_the_private_setup_code(): void
    {
        $this->claim();
        $this->postJson('/api/student-password-setup/complete', ['password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable();
        $this->postJson('/api/student-password-setup/complete', ['password' => 'PrivatePassword123!', 'password_confirmation' => 'different'])->assertUnprocessable();
        $this->assertNull(StudentSetupCode::where('user_id', $this->student->id)->first()->consumed_at);
        $this->assertSame('pending', $this->student->fresh()->status);
    }

    public function test_an_administrator_can_provision_a_student_without_an_email(): void
    {
        $class = LearningClass::firstOrFail();
        $this->actingAs($this->admin)->postJson('/api/admin/students', ['classId' => $class->id,
            'firstName' => 'QA', 'lastName' => 'New Learner', 'identityNumber' => 'QA-OPTIONAL-EMAIL'])->assertCreated()->assertJsonCount(0, 'activationCodes');
        $this->assertDatabaseHas('users', ['identity_number' => 'QA-OPTIONAL-EMAIL', 'email' => null, 'status' => 'pending']);
        $this->getJson('/api/admin/roster')->assertOk()->assertJsonPath('passwordSetupEnabled', true);
    }

    private function issue(): string
    {
        $code = $this->actingAs($this->admin)->postJson('/api/admin/student-setup-codes', ['userIds' => [$this->student->id]])->assertOk()->json('codes.0.code');
        auth()->logout();

        return $code;
    }

    private function claim(): string
    {
        $code = $this->issue();
        $this->postJson('/api/student-password-setup/claim', ['identityNumber' => 'QA-ID-ONLY', 'setupCode' => $code])->assertOk()->assertJsonPath('stage', 'password');

        return $code;
    }

    private function complete()
    {
        return $this->postJson('/api/student-password-setup/complete', ['password' => 'PrivatePassword123!', 'password_confirmation' => 'PrivatePassword123!']);
    }
}
