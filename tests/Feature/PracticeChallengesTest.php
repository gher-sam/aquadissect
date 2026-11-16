<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\ClassEnrollment;
use App\Models\ClassModuleAssignment;
use App\Models\LearningClass;
use App\Models\LearningModuleVersion;
use App\Models\LessonResult;
use App\Models\PracticeCompletion;
use App\Models\SpeciesAssignment;
use App\Models\SpeciesPackage;
use App\Models\User;
use App\Services\CloneRefresher;
use App\Services\PracticeChallenges;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PracticeChallengesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['species-packages.visible_slugs' => []]);
        config(['delayed-clone.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    private function student(): User
    {
        return User::where('role', 'student')->firstOrFail();
    }

    private function package(): SpeciesPackage
    {
        return SpeciesPackage::where('status', 'published')->where('version', '1.1.0')->firstOrFail();
    }

    private function section(): LearningClass
    {
        return LearningClass::where('code', 'FISHBIO-101')->firstOrFail();
    }

    private function url(?SpeciesPackage $package = null): string
    {
        return '/api/species-packages/'.($package ?? $this->package())->id.'/practice';
    }

    private function answer(array $challenge, array $overrides = []): array
    {
        return array_merge([
            'classId' => $this->section()->id,
            'packageVersion' => $this->package()->version,
            'challengeId' => $challenge['id'],
            'answer' => $challenge['answer'],
        ], $overrides);
    }

    public function test_practice_uses_package_content_without_exposing_answer_keys_or_changing_lesson_records(): void
    {
        $this->getJson($this->url())->assertUnauthorized();
        $result = $this->actingAs($this->student())->getJson($this->url())->assertOk()
            ->assertJsonCount(6, 'challenges')->assertJsonPath('preview', false)
            ->assertJsonPath('summary.xp', 0)->assertJsonPath('summary.maxXp', 60)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame('function', $result->json('challenges.0.kind'));
        $this->assertSame('system', $result->json('challenges.3.kind'));
        foreach ($result->json('challenges') as $challenge) {
            $this->assertArrayNotHasKey('answer', $challenge);
            $this->assertArrayNotHasKey('explanation', $challenge);
            $this->assertCount(4, $challenge['options']);
            $this->assertCount(4, array_unique($challenge['options']));
        }
        $this->assertSame(0, PracticeCompletion::count());
        $this->assertSame(0, LessonResult::count());
        $this->assertSame(0, AssessmentAttempt::count());
    }

    public function test_wrong_answers_do_not_award_points_and_retries_cannot_farm_rewards(): void
    {
        $challenge = app(PracticeChallenges::class)->definitions($this->package())[0];
        $wrong = collect($challenge['options'])->first(fn ($option) => $option !== $challenge['answer']);
        $this->actingAs($this->student())->postJson($this->url().'/answers', $this->answer($challenge, ['answer' => $wrong]))
            ->assertOk()->assertJsonPath('correct', false)->assertJsonPath('awardedXp', 0)->assertJsonPath('summary.completed', 0);
        $this->assertSame(0, PracticeCompletion::count());
        $this->postJson($this->url().'/answers', $this->answer($challenge))->assertOk()
            ->assertJsonPath('correct', true)->assertJsonPath('awardedXp', 10)
            ->assertJsonPath('summary.xp', 10)->assertJsonPath('summary.badges.0.earned', true);
        $this->postJson($this->url().'/answers', $this->answer($challenge))->assertOk()
            ->assertJsonPath('correct', true)->assertJsonPath('awardedXp', 0)->assertJsonPath('summary.xp', 10);
        $this->getJson($this->url())->assertOk()->assertJsonPath('summary.completed', 1)->assertJsonPath('summary.xp', 10);
        $this->assertSame(1, PracticeCompletion::count());
        $this->assertDatabaseHas('clone_outbox', ['table_name' => 'practice_completions']);
    }

    public function test_collection_is_earned_only_from_all_required_correct_challenges_and_stays_separate_from_assessments(): void
    {
        $this->actingAs($this->student());
        foreach (app(PracticeChallenges::class)->definitions($this->package()) as $challenge) {
            $result = $this->postJson($this->url().'/answers', $this->answer($challenge))->assertOk();
        }
        $result->assertJsonPath('summary.xp', 60)->assertJsonPath('summary.complete', true);
        $this->assertSame([true, true, true, true], array_column($result->json('summary.badges'), 'earned'));
        $this->assertSame(6, PracticeCompletion::count());
        $this->assertSame(0, LessonResult::count());
        $this->assertSame(0, AssessmentAttempt::count());
        $this->assertDatabaseCount('learning_module_progress', 0);
    }

    public function test_rewards_are_private_and_separate_between_sections(): void
    {
        $package = $this->package();
        $challenge = app(PracticeChallenges::class)->definitions($package)[0];
        $this->actingAs($this->student())->postJson($this->url().'/answers', $this->answer($challenge))->assertOk();
        $other = User::factory()->create(['first_name' => 'Practice', 'last_name' => 'Learner']);
        ClassEnrollment::create(['class_id' => $this->section()->id, 'user_id' => $other->id, 'status' => 'active']);
        $this->actingAs($other)->getJson($this->url())->assertOk()->assertJsonPath('summary.xp', 0);
        $second = LearningClass::create(['code' => 'QA-PRACTICE', 'name' => 'Synthetic practice section', 'status' => 'active', 'course_id' => $this->section()->course_id]);
        ClassEnrollment::create(['class_id' => $second->id, 'user_id' => $this->student()->id, 'status' => 'active']);
        SpeciesAssignment::create(['class_id' => $second->id, 'species_id' => $package->species_id, 'species_package_id' => $package->id, 'active' => true, 'assigned_at' => now()]);
        $this->actingAs($this->student())->getJson($this->url())->assertUnprocessable();
        $this->getJson($this->url().'?classId='.$second->id)->assertOk()->assertJsonPath('summary.xp', 0);
        $this->postJson($this->url().'/answers', $this->answer($challenge, ['classId' => $second->id]))
            ->assertOk()->assertJsonPath('awardedXp', 10)->assertJsonPath('summary.xp', 10);
        $this->getJson($this->url().'?classId='.$this->section()->id)->assertOk()->assertJsonPath('summary.xp', 10);
        $this->assertSame(2, PracticeCompletion::count());
    }

    public function test_unassigned_unpublished_inactive_and_safety_locked_access_is_rejected(): void
    {
        $outsider = User::factory()->create(['first_name' => 'Unassigned', 'last_name' => 'Learner']);
        $this->actingAs($outsider)->getJson($this->url())->assertForbidden();
        $challenge = app(PracticeChallenges::class)->definitions($this->package())[0];
        $this->postJson($this->url().'/answers', $this->answer($challenge))->assertForbidden();
        $draft = SpeciesPackage::where('status', 'review')->firstOrFail();
        $this->actingAs($this->student())->getJson($this->url($draft))->assertForbidden();
        $this->postJson($this->url($draft).'/answers', $this->answer($challenge, ['packageVersion' => $draft->version]))->assertForbidden();
        $version = LearningModuleVersion::firstOrFail();
        DB::table('learning_module_versions')->where('id', $version->id)->update(['status' => 'published']);
        ClassModuleAssignment::create(['class_id' => $this->section()->id, 'learning_module_id' => $version->learning_module_id,
            'learning_module_version_id' => $version->id, 'required' => true, 'active' => true, 'assigned_at' => now()]);
        $this->getJson($this->url())->assertUnprocessable()->assertJsonValidationErrors('prerequisite');
        $this->postJson($this->url().'/answers', $this->answer($challenge))->assertUnprocessable();
        $this->assertSame(0, PracticeCompletion::count());
        $this->student()->update(['status' => 'pending']);
        $this->actingAs($this->student()->fresh())->getJson($this->url())->assertForbidden();
    }

    public function test_client_points_unknown_questions_answers_and_stale_versions_are_rejected(): void
    {
        $challenge = app(PracticeChallenges::class)->definitions($this->package())[0];
        $this->actingAs($this->student());
        foreach ([['xp' => 900], ['score' => 999], ['challengeId' => 'invented'], ['answer' => 'Outside options'], ['packageVersion' => 'stale']] as $override) {
            $this->postJson($this->url().'/answers', $this->answer($challenge, $override))->assertUnprocessable();
        }
        $this->assertSame(0, PracticeCompletion::count());
    }

    public function test_access_is_checked_again_when_an_enrollment_or_assignment_changes(): void
    {
        $challenge = app(PracticeChallenges::class)->definitions($this->package())[0];
        $student = $this->student();
        $this->actingAs($student)->getJson($this->url())->assertOk();
        ClassEnrollment::where('user_id', $student->id)->update(['status' => 'archived']);
        $this->postJson($this->url().'/answers', $this->answer($challenge))->assertForbidden();
        ClassEnrollment::where('user_id', $student->id)->update(['status' => 'active']);
        SpeciesAssignment::where('class_id', $this->section()->id)->update(['active' => false]);
        $this->postJson($this->url().'/answers', $this->answer($challenge))->assertForbidden();
        $this->assertSame(0, PracticeCompletion::count());
    }

    public function test_required_password_change_blocks_practice_and_reward_submission(): void
    {
        $student = $this->student();
        $student->forceFill(['must_change_password' => true])->save();
        $challenge = app(PracticeChallenges::class)->definitions($this->package())[0];
        $this->actingAs($student)->withSession(['student_password_revision' => 0])
            ->getJson($this->url())->assertForbidden()->assertJsonPath('passwordChangeRequired', true);
        $this->postJson($this->url().'/answers', $this->answer($challenge))->assertForbidden();
        $this->assertSame(0, PracticeCompletion::count());
    }

    public function test_staff_can_preview_without_saving_rewards_or_student_records(): void
    {
        foreach (['admin', 'instructor', 'program_head'] as $role) {
            $staff = User::factory()->create(['role' => $role, 'first_name' => 'Practice', 'last_name' => 'Reviewer']);
            $draft = SpeciesPackage::where('status', 'review')->firstOrFail();
            $this->actingAs($staff)->getJson($this->url($draft))->assertOk()->assertJsonPath('preview', true);
            $challenge = app(PracticeChallenges::class)->definitions($draft)[0];
            $this->postJson($this->url($draft).'/answers', $this->answer($challenge, ['packageVersion' => $draft->version]))
                ->assertOk()->assertJsonPath('correct', true)->assertJsonPath('preview', true)->assertJsonPath('awardedXp', 0);
        }
        $this->assertSame(0, PracticeCompletion::count());
        $this->assertSame(0, LessonResult::count());
    }

    public function test_rewards_reach_the_delayed_clone_and_cannot_be_overwritten(): void
    {
        config(['database.connections.sqlite_clone.database' => ':memory:', 'delayed-clone.connection' => 'sqlite_clone']);
        DB::purge('sqlite_clone');
        $this->artisan('migrate', ['--database' => 'sqlite_clone', '--force' => true])->assertSuccessful();
        $challenge = app(PracticeChallenges::class)->definitions($this->package())[0];
        $this->actingAs($this->student())->postJson($this->url().'/answers', $this->answer($challenge))->assertOk();
        app(CloneRefresher::class)->refresh();
        $reward = PracticeCompletion::firstOrFail();
        $this->assertSame(10, DB::connection('sqlite_clone')->table('practice_completions')->where('id', $reward->id)->value('points'));
        try {
            $reward->update(['points' => 500]);
            $this->fail('A practice reward was overwritten.');
        } catch (\LogicException $error) {
            $this->assertStringContainsString('cannot be overwritten', $error->getMessage());
        }
        $this->assertSame(10, $reward->fresh()->points);
        DB::purge('sqlite_clone');
    }

    public function test_application_rollback_and_reapplication_retain_existing_rewards(): void
    {
        $challenge = app(PracticeChallenges::class)->definitions($this->package())[0];
        $this->actingAs($this->student())->postJson($this->url().'/answers', $this->answer($challenge))->assertOk();
        $migration = require database_path('migrations/2026_11_16_000001_create_practice_completions.php');
        $migration->down();
        $this->assertSame(1, PracticeCompletion::count());
        $migration->up();
        $this->getJson($this->url())->assertOk()->assertJsonPath('summary.xp', 10);
        $this->postJson($this->url().'/answers', $this->answer($challenge))->assertOk()->assertJsonPath('awardedXp', 0);
        $this->assertSame(1, PracticeCompletion::count());
    }

    public function test_earning_practice_invalidates_a_pending_enrollment_deletion_and_protects_its_history(): void
    {
        $student = $this->student();
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->withSession(['practice_review_test' => true]);
        $this->withCredentials();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        $enrollment = ClassEnrollment::where('user_id', $student->id)->where('class_id', $this->section()->id)->firstOrFail();
        $input = ['resource' => 'enrollments', 'action' => 'delete', 'ids' => [$enrollment->id], 'reason' => 'Synthetic history protection check.'];
        $preview = $this->actingAs($admin)->postJson('/api/admin/records/preview', $input)
            ->assertOk()->assertJsonPath('allowed', true)->json();
        $challenge = app(PracticeChallenges::class)->definitions($this->package())[0];
        $this->actingAs($student)->postJson($this->url().'/answers', $this->answer($challenge))->assertOk();
        $this->actingAs($admin)->postJson('/api/admin/records/execute', [
            'token' => $preview['token'], 'confirmation' => 'DELETE', 'requestUuid' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertConflict();
        $this->postJson('/api/admin/records/preview', $input)->assertOk()->assertJsonPath('allowed', false);
        $this->assertDatabaseHas('class_enrollments', ['id' => $enrollment->id]);
        $this->assertSame(1, PracticeCompletion::count());
    }
}
