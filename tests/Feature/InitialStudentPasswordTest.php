<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\ClassInstructor;
use App\Models\LearningClass;
use App\Models\LessonResult;
use App\Models\RosterEvent;
use App\Models\User;
use App\Services\StudentPasswords;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InitialStudentPasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private User $student;

    private LearningClass $class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->teacher = User::where('role', 'instructor')->firstOrFail();
        $this->class = LearningClass::firstOrFail();
        $this->student = User::factory()->create(['first_name' => 'QA', 'last_name' => 'Learner', 'identity_number' => 'QA-FIRST-001',
            'email' => null, 'email_verified_at' => null, 'role' => 'student', 'status' => 'pending']);
        ClassEnrollment::create(['class_id' => $this->class->id, 'user_id' => $this->student->id, 'status' => 'active']);
        ClassInstructor::firstOrCreate(['class_id' => $this->class->id, 'user_id' => $this->teacher->id]);
        config(['student-setup.initial_password_enabled' => true, 'student-setup.password_enabled' => false,
            'student-setup.enabled' => false, 'student-setup.delivery_verified' => false]);
        Mail::fake();
    }

    private function initialize(): void
    {
        $this->assertTrue(app(StudentPasswords::class)->initialize($this->student, $this->admin, $this->class->id));
        $this->student->refresh();
    }

    private function loginTemporary(): void
    {
        auth()->logout();
        $this->postJson('/api/login', ['identifier' => 'qa-first-001', 'password' => 'QA-FIRST-001'])->assertOk()
            ->assertJsonPath('passwordChangeRequired', true)->assertJsonMissingPath('classes')->assertJsonMissingPath('species')->assertJsonMissingPath('lessonResult');
    }

    public function test_id_as_initial_password_requires_change_before_course_access_and_preserves_records(): void
    {
        $this->initialize();
        $originalId = $this->student->id;
        $this->assertTrue(Hash::check('QA-FIRST-001', $this->student->password));
        $this->assertNotSame('QA-FIRST-001', $this->student->getRawOriginal('password'));
        $this->loginTemporary();
        $this->get('/dashboard')->assertRedirect('/change-password');
        $this->get('/offline')->assertRedirect('/change-password');
        foreach (['species', 'classes', 'reports', 'tutorials', 'session', 'learning-modules', 'species-packages/1/download'] as $path) {
            $this->getJson('/api/'.$path)->assertForbidden()->assertJsonPath('passwordChangeRequired', true);
        }
        $this->postJson('/api/lesson-results', [])->assertForbidden();
        $this->get('/change-password')->assertOk()->assertSee('Choose a new password')->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson('/api/change-password', ['password' => 'PrivateCourse123!', 'password_confirmation' => 'PrivateCourse123!'])->assertOk();
        $this->student->refresh();
        $this->assertSame('active', $this->student->status);
        $this->assertFalse($this->student->must_change_password);
        $this->assertSame(2, $this->student->password_revision);
        $this->assertNotNull($this->student->password_changed_at);
        $this->assertSame($originalId, $this->student->id);
        $this->assertNull($this->student->email);
        $this->assertNull($this->student->email_verified_at);
        $this->assertDatabaseHas('class_enrollments', ['class_id' => $this->class->id, 'user_id' => $originalId, 'status' => 'active']);
        $this->get('/dashboard')->assertOk();
        $this->getJson('/api/classes')->assertOk()->assertJsonPath('classes.0.id', $this->class->id);
        $this->getJson('/api/species')->assertOk()->assertJsonCount(0, 'species');
        $this->assertDatabaseHas('roster_events', ['user_id' => $originalId, 'actor_id' => $originalId, 'action' => 'student_password_changed']);
        $this->assertDatabaseHas('roster_events', ['user_id' => $originalId, 'action' => 'student_activated']);
        $this->postJson('/api/logout')->assertOk();
        $this->postJson('/api/login', ['identifier' => 'QA-FIRST-001', 'password' => 'QA-FIRST-001'])->assertUnprocessable();
        $this->postJson('/api/login', ['identifier' => 'QA-FIRST-001', 'password' => 'PrivateCourse123!'])->assertOk();
        Mail::assertNothingSent();
    }

    public function test_new_roster_accounts_get_the_initial_password_but_reimport_never_resets_existing_accounts(): void
    {
        $payload = ['classId' => $this->class->id, 'firstName' => 'QA', 'lastName' => 'New Learner', 'identityNumber' => 'QA-INITIAL-NEW'];
        $this->actingAs($this->admin)->postJson('/api/admin/students', $payload)->assertCreated()->assertJsonCount(0, 'activationCodes');
        $student = User::where('identity_number', 'QA-INITIAL-NEW')->firstOrFail();
        $this->assertTrue($student->must_change_password);
        $this->assertTrue(Hash::check('QA-INITIAL-NEW', $student->password));
        $password = $student->password;
        $this->postJson('/api/admin/students', $payload)->assertCreated();
        $this->assertSame($password, $student->fresh()->password);
        $this->assertSame(1, RosterEvent::where('user_id', $student->id)->where('action', 'student_initial_password_set')->count());
    }

    public function test_new_password_must_be_confirmed_strong_and_different_from_the_student_id(): void
    {
        $this->initialize();
        $this->loginTemporary();
        foreach ([['short', 'short'], ['PrivateCourse123!', 'different'], ['QA-FIRST-001', 'QA-FIRST-001'], ['qa-first-001', 'qa-first-001']] as [$password, $confirmation]) {
            $this->postJson('/api/change-password', ['password' => $password, 'password_confirmation' => $confirmation])->assertUnprocessable();
        }
        $this->assertTrue($this->student->fresh()->must_change_password);
        $this->assertSame('pending', $this->student->fresh()->status);
        $this->assertDatabaseMissing('roster_events', ['user_id' => $this->student->id, 'action' => 'student_password_changed']);
    }

    public function test_native_password_form_changes_the_password_and_redirects_to_the_course(): void
    {
        $this->initialize();
        $this->loginTemporary();
        $this->post('/change-password', ['password' => 'PrivateNative123!', 'password_confirmation' => 'PrivateNative123!'])->assertRedirect('/dashboard')->assertStatus(303);
        $this->assertFalse($this->student->fresh()->must_change_password);
        $this->get('/change-password')->assertRedirect('/dashboard');
    }

    public function test_admin_and_assigned_teacher_can_reset_with_scoped_audit_history_and_no_passwords(): void
    {
        $this->student->update(['status' => 'active', 'password' => 'ExistingPrivate123!']);
        $result = LessonResult::create(['user_id' => $this->student->id, 'lesson_key' => 'qa-retained', 'score' => 3, 'total' => 5, 'completed_at' => now()]);
        foreach ([$this->admin, $this->teacher] as $actor) {
            $this->actingAs($actor)->postJson('/api/staff/students/'.$this->student->id.'/reset-password', ['classId' => $this->class->id, 'reason' => 'student_request'])->assertOk();
            $this->assertTrue(Hash::check('QA-FIRST-001', $this->student->fresh()->password));
            $this->assertTrue($this->student->fresh()->must_change_password);
            $this->assertSame('active', $this->student->fresh()->status);
            $this->getJson('/api/staff/students/'.$this->student->id.'/password-history?classId='.$this->class->id)->assertOk()
                ->assertJsonPath('events.0.actorId', $actor->id)->assertJsonPath('events.0.reason', 'student_request');
        }
        $this->assertSame(2, RosterEvent::where('user_id', $this->student->id)->where('action', 'student_password_reset')->count());
        $events = RosterEvent::where('user_id', $this->student->id)->get()->toJson();
        $this->assertStringNotContainsString('QA-FIRST-001', $events);
        $this->assertStringNotContainsString('ExistingPrivate123!', $events);
        $this->assertDatabaseHas('lesson_results', ['id' => $result->id, 'score' => 3]);
        $this->assertDatabaseHas('class_enrollments', ['class_id' => $this->class->id, 'user_id' => $this->student->id, 'status' => 'active']);
    }

    public function test_unassigned_teachers_program_heads_students_and_guests_cannot_reset_or_view_history(): void
    {
        $url = '/api/staff/students/'.$this->student->id;
        $this->postJson($url.'/reset-password', ['classId' => $this->class->id, 'reason' => 'student_request'])->assertUnauthorized();
        foreach (['instructor', 'program_head', 'student'] as $role) {
            $actor = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($actor)->postJson($url.'/reset-password', ['classId' => $this->class->id, 'reason' => 'student_request'])->assertForbidden();
            $this->getJson($url.'/password-history?classId='.$this->class->id)->assertForbidden();
        }
        $this->assertFalse($this->student->fresh()->must_change_password);
        $this->assertDatabaseMissing('roster_events', ['action' => 'student_password_reset']);
    }

    public function test_teacher_cannot_reset_using_an_archived_class_or_inactive_enrollment_or_staff_target(): void
    {
        $this->class->update(['status' => 'archived']);
        $this->actingAs($this->teacher)->postJson('/api/staff/students/'.$this->student->id.'/reset-password', ['classId' => $this->class->id, 'reason' => 'forgot_password'])->assertForbidden();
        $this->class->update(['status' => 'active']);
        ClassEnrollment::where('user_id', $this->student->id)->update(['status' => 'archived']);
        $this->postJson('/api/staff/students/'.$this->student->id.'/reset-password', ['classId' => $this->class->id, 'reason' => 'forgot_password'])->assertForbidden();
        $this->actingAs($this->admin)->postJson('/api/staff/students/'.$this->teacher->id.'/reset-password', ['reason' => 'student_request'])->assertForbidden();
    }

    public function test_reset_invalidates_old_sessions_and_a_second_temporary_session_after_password_change(): void
    {
        $this->student->update(['status' => 'active', 'password' => 'ExistingPrivate123!']);
        $this->postJson('/api/login', ['identifier' => 'QA-FIRST-001', 'password' => 'ExistingPrivate123!'])->assertOk();
        $oldRevision = session('student_password_revision');
        app(StudentPasswords::class)->reset($this->student, $this->admin, 'forgot_password');
        $this->actingAs($this->student->fresh())->withSession(['student_password_revision' => $oldRevision])->getJson('/api/classes')->assertUnauthorized();
        $this->loginTemporary();
        $temporaryRevision = session('student_password_revision');
        $this->postJson('/api/change-password', ['password' => 'AfterReset123!', 'password_confirmation' => 'AfterReset123!'])->assertOk();
        $this->actingAs($this->student->fresh())->withSession(['student_password_revision' => $temporaryRevision])->getJson('/api/classes')->assertUnauthorized();
        $this->assertTrue(Hash::check('AfterReset123!', $this->student->fresh()->password));
    }

    public function test_reset_between_login_and_password_submission_cannot_be_overwritten(): void
    {
        $this->initialize();
        $revision = $this->student->password_revision;
        app(StudentPasswords::class)->reset($this->student, $this->admin, 'student_request');
        try {
            app(StudentPasswords::class)->change($this->student, $revision, 'StaleSubmission123!');
            $this->fail('A stale password-change request must fail.');
        } catch (ValidationException) {
            $this->assertTrue(Hash::check('QA-FIRST-001', $this->student->fresh()->password));
            $this->assertTrue($this->student->fresh()->must_change_password);
        }
    }

    public function test_reset_reason_is_controlled_and_default_existing_student_access_is_preserved(): void
    {
        $active = User::where('role', 'student')->where('status', 'active')->firstOrFail();
        $password = $active->password;
        $this->actingAs($this->admin)->postJson('/api/staff/students/'.$this->student->id.'/reset-password', ['reason' => 'arbitrary-secret-text'])->assertUnprocessable();
        $this->assertFalse(app(StudentPasswords::class)->initialize($active, $this->admin));
        $this->assertSame($password, $active->fresh()->password);
        $this->actingAs($active)->getJson('/api/classes')->assertOk();
        $this->assertDatabaseMissing('roster_events', ['action' => 'student_password_reset']);
    }

    public function test_initialization_command_previews_and_only_changes_pending_students_in_selected_sections(): void
    {
        $this->class->update(['academic_year' => '2026-2027', 'semester' => '1', 'section_code' => 'A']);
        $active = User::where('role', 'student')->where('status', 'active')->firstOrFail();
        $oldPassword = $active->password;
        $options = ['--actor' => $this->admin->id, '--academic-year' => '2026-2027', '--semester' => '1', '--section' => ['A']];
        $this->artisan('students:initialize-passwords', $options)->expectsOutput('Eligible pending students: 1')->assertSuccessful();
        $this->assertFalse($this->student->fresh()->must_change_password);
        $this->artisan('students:initialize-passwords', [...$options, '--apply' => true])->assertSuccessful();
        $this->assertTrue($this->student->fresh()->must_change_password);
        $this->assertSame($oldPassword, $active->fresh()->password);
        $this->artisan('students:initialize-passwords', [...$options, '--apply' => true])->expectsOutput('Eligible pending students: 0')->assertSuccessful();
        $this->assertSame(1, RosterEvent::where('user_id', $this->student->id)->where('action', 'student_initial_password_set')->count());
    }
}
