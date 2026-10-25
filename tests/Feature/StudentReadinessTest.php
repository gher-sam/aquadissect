<?php

namespace Tests\Feature;

use App\Models\AccountActivationToken;
use App\Models\ClassEnrollment;
use App\Models\ClassModuleAssignment;
use App\Models\LearningClass;
use App\Models\LearningModuleVersion;
use App\Models\RosterEvent;
use App\Models\User;
use App\Services\LearningModuleLifecycle;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_adding_a_missing_email_preserves_account_and_enrollment_and_requires_separate_activation(): void
    {
        $student = $this->pendingStudent();
        $class = LearningClass::firstOrFail();
        ClassEnrollment::create(['class_id' => $class->id, 'user_id' => $student->id, 'status' => 'active']);
        $before = $student->getRawOriginal();
        $this->actingAs($this->admin())->patchJson("/api/admin/students/{$student->id}/email", [
            'email' => 'QA.READINESS@example.test', 'identityNumber' => $student->identity_number,
        ])->assertOk();
        $student->refresh();
        $this->assertSame('qa.readiness@example.test', $student->email);
        foreach (['password', 'status', 'role', 'identity_number', 'first_name', 'last_name', 'remember_token'] as $field) {
            $this->assertSame($before[$field] ?? null, $student->getRawOriginal($field));
        }
        $this->assertNull($student->email_verified_at);
        $this->assertSame(0, AccountActivationToken::where('user_id', $student->id)->count());
        $this->assertDatabaseHas('class_enrollments', ['class_id' => $class->id, 'user_id' => $student->id, 'status' => 'active']);
        $this->assertDatabaseHas('roster_events', ['action' => 'student_email_added', 'user_id' => $student->id, 'actor_id' => $this->admin()->id]);
        $this->assertDatabaseHas('clone_outbox', ['table_name' => 'users', 'record_key' => (string) $student->id, 'operation' => 'update']);
        $code = $this->postJson("/api/admin/students/{$student->id}/activation")->assertOk()->json('code');
        auth()->logout();
        $this->postJson('/api/activate', ['email' => $student->email, 'code' => $code, 'password' => 'ReadinessTest123!', 'password_confirmation' => 'ReadinessTest123!'])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $student->id, 'status' => 'active']);
    }

    public function test_email_completion_rejects_unauthorized_invalid_conflicting_and_stale_requests(): void
    {
        $student = $this->pendingStudent();
        $url = "/api/admin/students/{$student->id}/email";
        $payload = ['email' => 'readiness@example.test', 'identityNumber' => $student->identity_number];
        $this->patchJson($url, $payload)->assertUnauthorized();
        foreach (['student', 'instructor', 'program_head'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'active']))->patchJson($url, $payload)->assertForbidden();
        }
        $this->actingAs($this->admin())->patchJson($url, [...$payload, 'email' => 'invalid'])->assertUnprocessable();
        $this->patchJson($url, [...$payload, 'identityNumber' => 'WRONG-ID'])->assertUnprocessable();
        $this->patchJson($url, [...$payload, 'email' => strtoupper($this->admin()->email)])->assertUnprocessable();
        $this->assertNull($student->fresh()->email);
        $this->assertSame(0, RosterEvent::where('action', 'student_email_added')->count());
        $this->patchJson($url, $payload)->assertOk();
        $this->patchJson($url, [...$payload, 'email' => 'replacement@example.test'])->assertUnprocessable();
        $this->assertSame('readiness@example.test', $student->fresh()->email);
        $active = $this->pendingStudent(['status' => 'active']);
        $this->patchJson("/api/admin/students/{$active->id}/email", [...$payload, 'email' => 'active@example.test', 'identityNumber' => $active->identity_number])->assertUnprocessable();
        $staff = $this->pendingStudent(['role' => 'instructor']);
        $this->patchJson("/api/admin/students/{$staff->id}/email", [...$payload, 'email' => 'staff@example.test', 'identityNumber' => $staff->identity_number])->assertUnprocessable();
    }

    public function test_publication_updates_only_selected_sections_and_keeps_unselected_assignments(): void
    {
        $version = $this->approvedModule();
        $class = LearningClass::firstOrFail();
        $other = $class->replicate();
        $other->fill(['code' => 'QA-FUTURE', 'academic_year' => '2027-2028', 'section_code' => 'QA'])->save();
        $old = LearningModuleVersion::where('version', '0.1.0')->firstOrFail();
        $assignment = ClassModuleAssignment::create(['class_id' => $other->id, 'learning_module_id' => $old->learning_module_id, 'learning_module_version_id' => $old->id, 'required' => true, 'active' => true, 'assigned_by' => $this->admin()->id, 'assigned_at' => now()]);
        $before = $assignment->fresh()->getRawOriginal();
        $this->actingAs($this->admin())->postJson("/api/admin/learning-module-versions/{$version->id}/publish", ['classIds' => [$class->id]])
            ->assertOk()->assertJsonPath('assignments', 1);
        $this->assertSame($before, $assignment->fresh()->getRawOriginal());
        $this->assertDatabaseHas('class_module_assignments', ['class_id' => $class->id, 'learning_module_version_id' => $version->id]);
    }

    public function test_publication_requires_explicit_valid_selection_and_can_publish_without_assigning(): void
    {
        $version = $this->approvedModule();
        $url = "/api/admin/learning-module-versions/{$version->id}/publish";
        $class = LearningClass::firstOrFail();
        $this->actingAs($this->admin())->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('classIds');
        $this->postJson($url, ['classIds' => [$class->id, $class->id]])->assertUnprocessable();
        $this->postJson($url, ['classIds' => [999999]])->assertUnprocessable();
        $class->update(['status' => 'archived']);
        $this->postJson($url, ['classIds' => [$class->id]])->assertUnprocessable();
        $this->assertSame('review', $version->fresh()->status);
        $this->assertDatabaseCount('class_module_assignments', 0);
        $this->postJson($url, ['classIds' => []])->assertOk()->assertJsonPath('assignments', 0);
        $this->assertDatabaseCount('class_module_assignments', 0);
    }

    private function pendingStudent(array $attributes = []): User
    {
        return User::factory()->create([...['first_name' => 'QA', 'last_name' => 'Learner', 'role' => 'student', 'status' => 'pending', 'email' => null, 'email_verified_at' => null, 'identity_number' => 'QA-'.bin2hex(random_bytes(4))], ...$attributes]);
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function approvedModule(): LearningModuleVersion
    {
        $lifecycle = app(LearningModuleLifecycle::class);
        $version = LearningModuleVersion::where('version', '0.2.0')->firstOrFail();
        $lifecycle->submit($version, $this->admin());

        return $lifecycle->review($version->fresh(), User::where('role', 'instructor')->firstOrFail(), 'approved', 'Synthetic QA-only review; not faculty validation.', 'QA fixture only', true);
    }
}
