<?php

namespace Tests\Feature;

use App\Models\{User, TutorialProgress, RoleChangeEvent, ClassInstructor, LearningClass, LearningModuleVersion, SpeciesPackage, CloneOutbox};
use App\Services\{TutorialCatalog, DelayedCloneApplier, CloneRefresher};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, DB};
use Tests\TestCase;

class TutorialAndProgramHeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function person(string $role): User
    {
        return User::where('role', $role)->first() ?? User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    public function test_catalog_is_role_scoped_and_progress_is_private_validated_and_idempotent(): void
    {
        $this->getJson('/api/tutorials')->assertUnauthorized();
        foreach (['student' => 5, 'instructor' => 5, 'program_head' => 4, 'admin' => 5] as $role => $count) {
            $this->actingAs($this->person($role))->getJson('/api/tutorials')->assertOk()->assertJsonCount($count, 'guides');
            foreach (app(TutorialCatalog::class)->guides($role) as $guide) {
                $this->assertSame($role, $guide['role']);
                $this->assertNotEmpty($guide['prerequisite']);
                $this->assertCount(count($guide['steps']), array_unique(array_column($guide['steps'], 'id')));
                foreach ($guide['steps'] as $step) $this->assertArrayHasKey($step['page'], \App\Support\PortalPages::PAGES);
            }
        }
        $student = $this->person('student');
        $this->actingAs($student);
        $url = '/api/tutorials/student-class/progress';
        $payload = ['version' => 1, 'step' => 'activities', 'viewed' => true];
        $this->putJson($url, $payload)->assertOk();
        $first = TutorialProgress::firstOrFail();
        $this->travel(5)->minutes();
        $this->putJson($url, $payload)->assertOk();
        $this->assertDatabaseCount('tutorial_progress', 1);
        $this->assertTrue($first->viewed_at->equalTo($first->fresh()->viewed_at));
        $this->getJson('/api/tutorials')->assertJsonPath('progress.0.step_key', 'activities');
        $this->putJson($url, [...$payload, 'step' => 'unknown'])->assertUnprocessable();
        $this->putJson($url, [...$payload, 'version' => 99])->assertUnprocessable();
        $this->putJson('/api/tutorials/admin-head/progress', $payload)->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'student', 'status' => 'active']))->getJson('/api/tutorials')->assertJsonCount(0, 'progress');
        $this->assertDatabaseCount('lesson_results', 0);
        $this->assertDatabaseCount('learning_module_progress', 0);
    }

    public function test_program_head_has_oversight_without_administrator_or_teaching_mutations(): void
    {
        $head = $this->person('program_head');
        $this->actingAs($head);
        foreach (['dashboard', 'lessons', 'safety-tools', 'class-sections', 'species-content', 'calendar', 'notifications', 'reports', 'help', 'account-roles'] as $page) $this->get('/'.$page)->assertOk();
        foreach (['people-sections', 'activities', 'media-prompts', 'source-handover', 'offline-access'] as $page) $this->get('/'.$page)->assertForbidden();
        $this->getJson('/api/classes')->assertOk()->assertJsonCount(LearningClass::count(), 'classes');
        $this->getJson('/api/reports')->assertOk();
        $this->get('/api/reports?format=csv')->assertOk();
        $this->getJson('/api/calendar?from=2026-09-01T00:00:00Z&to=2026-10-01T00:00:00Z')->assertOk();
        foreach (['/api/admin/roster', '/api/admin/staff-roles', '/api/admin/source-handover'] as $url) $this->getJson($url)->assertForbidden();
        $class = LearningClass::firstOrFail();
        $version = LearningModuleVersion::firstOrFail();
        $package = SpeciesPackage::firstOrFail();
        foreach ([
            '/api/admin/offerings', '/api/admin/students', '/api/staff/media-prompts/preview',
            "/api/instructor/classes/{$class->id}/assignments", "/api/staff/classes/{$class->id}/learning-modules",
            "/api/staff/classes/{$class->id}/learning-modules/{$version->learning_module_id}/overrides",
            "/api/staff/learning-module-versions/{$version->id}/submit", "/api/staff/learning-module-versions/{$version->id}/assets",
            "/api/admin/learning-module-versions/{$version->id}/publish", "/api/admin/species-packages/{$package->id}/publish",
            '/api/lesson-results', '/api/lessons/example/attempts',
        ] as $url) $this->postJson($url, [])->assertForbidden();
        $this->getJson("/api/learning-modules/{$version->id}/manifest")->assertForbidden();
        $this->getJson('/api/learning-modules')->assertJsonCount(0, 'learningModules');
        $this->get('/offline')->assertDontSee('head-overview')->assertDontSee('staffRoleTools');
    }

    public function test_head_can_review_safety_but_cannot_approve_own_upload_or_publish(): void
    {
        $head = $this->person('program_head');
        $version = LearningModuleVersion::firstOrFail();
        $version->update(['status' => 'review']);
        $payload = ['decision' => 'approved', 'notes' => 'Checked the complete laboratory module against the faculty procedure.', 'evidenceReference' => 'Test faculty procedure v1', 'localSopConfirmed' => true];
        $this->actingAs($head)->getJson("/api/learning-modules/{$version->id}/manifest")->assertOk();
        $this->postJson("/api/staff/learning-module-versions/{$version->id}/review", $payload)->assertOk();
        $this->assertDatabaseHas('learning_module_reviews', ['reviewer_id' => $head->id, 'decision' => 'approved']);
        $this->postJson("/api/admin/learning-module-versions/{$version->id}/publish")->assertForbidden();
        $version->update(['imported_by' => $head->id]);
        $this->postJson("/api/staff/learning-module-versions/{$version->id}/review", $payload)->assertUnprocessable();
    }

    public function test_head_can_record_species_review_with_existing_evidence_rules(): void
    {
        $head = $this->person('program_head');
        $package = SpeciesPackage::where('status', 'review')->firstOrFail();
        $payload = ['decision' => 'changes_requested', 'notes' => 'Clarify the specimen handling directions before publication.', 'curriculumAlignmentConfirmed' => false, 'safetyReviewConfirmed' => false];
        $this->actingAs($head)->postJson("/api/staff/species-packages/{$package->id}/review", $payload)->assertOk();
        $this->assertDatabaseHas('species_package_reviews', ['reviewer_id' => $head->id, 'decision' => 'changes_requested']);
        $this->assertSame('draft', $package->fresh()->status);
        $this->getJson("/api/species/{$package->species->slug}/packages/{$package->id}/manifest")->assertForbidden();
    }

    public function test_only_admin_can_change_active_staff_roles_and_assignments_are_preserved(): void
    {
        $staff = $this->person('instructor');
        $admin = $this->person('admin');
        $assignmentIds = ClassInstructor::where('user_id', $staff->id)->pluck('id')->all();
        $url = "/api/admin/staff-roles/{$staff->id}";
        foreach (['student', 'instructor', 'program_head'] as $role) $this->actingAs($this->person($role))->patchJson($url, ['role' => 'program_head', 'confirmed' => true])->assertForbidden();
        $this->actingAs($admin)->patchJson($url, ['role' => 'program_head'])->assertUnprocessable();
        $this->patchJson($url, ['role' => 'admin', 'confirmed' => true])->assertUnprocessable();
        $this->patchJson($url, ['role' => 'program_head', 'confirmed' => true])->assertOk();
        $this->patchJson($url, ['role' => 'program_head', 'confirmed' => true])->assertOk();
        $this->assertDatabaseCount('role_change_events', 1);
        $this->assertSame($assignmentIds, ClassInstructor::where('user_id', $staff->id)->pluck('id')->all());
        $this->actingAs($staff->fresh())->getJson('/api/session')->assertJsonPath('user.role', 'program_head');
        $this->postJson('/api/instructor/species-packages', [])->assertForbidden();
        $this->actingAs($admin)->patchJson($url, ['role' => 'instructor', 'confirmed' => true])->assertOk();
        $this->assertDatabaseCount('role_change_events', 2);
        $this->patchJson('/api/admin/staff-roles/'.$admin->id, ['role' => 'program_head', 'confirmed' => true])->assertForbidden();
        $student = $this->person('student');
        $this->patchJson('/api/admin/staff-roles/'.$student->id, ['role' => 'program_head', 'confirmed' => true])->assertUnprocessable();
        $staff->update(['status' => 'pending']);
        $this->patchJson($url, ['role' => 'program_head', 'confirmed' => true])->assertUnprocessable();
        $this->actingAs($staff->fresh())->getJson('/api/tutorials')->assertForbidden();
    }

    public function test_tutorial_and_role_history_reach_clone_only_after_delay(): void
    {
        config(['database.connections.sqlite_clone.database' => ':memory:', 'delayed-clone.connection' => 'sqlite_clone', 'delayed-clone.delay_hours' => 12]);
        DB::purge('sqlite_clone');
        Artisan::call('migrate:fresh', ['--database' => 'sqlite_clone', '--force' => true]);
        app(CloneRefresher::class)->refresh();
        $student = $this->person('student');
        $this->actingAs($student)->putJson('/api/tutorials/student-class/progress', ['version' => 1, 'step' => 'sections', 'viewed' => false])->assertOk();
        $this->actingAs($this->person('admin'))->patchJson('/api/admin/staff-roles/'.$this->person('instructor')->id, ['role' => 'program_head', 'confirmed' => true])->assertOk();
        $this->assertSame(0, DB::connection('sqlite_clone')->table('tutorial_progress')->count());
        $this->assertSame(0, DB::connection('sqlite_clone')->table('role_change_events')->count());
        $this->assertTrue(CloneOutbox::where('table_name', 'tutorial_progress')->exists());
        $this->travel(13)->hours();
        $result = app(DelayedCloneApplier::class)->applyEligible(1000);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(1, DB::connection('sqlite_clone')->table('tutorial_progress')->count());
        $this->assertSame(1, DB::connection('sqlite_clone')->table('role_change_events')->count());
        DB::purge('sqlite_clone');
    }
}
