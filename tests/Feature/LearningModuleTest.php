<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\ClassModuleAssignment;
use App\Models\LearningClass;
use App\Models\LearningModuleOverride;
use App\Models\LearningModuleVersion;
use App\Models\LessonResult;
use App\Models\MediaPromptRecord;
use App\Models\SpeciesPackage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LearningModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class LearningModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Keep retained-species prerequisite regression scenarios independent of current presentation scope.
        config(['species-packages.visible_slugs' => []]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_draft_is_staff_only_and_contains_the_complete_tool_register(): void
    {
        $student = $this->student();
        $instructor = $this->instructor();
        $version = $this->version();

        $this->actingAs($student)->getJson('/api/learning-modules')
            ->assertOk()->assertJsonCount(0, 'learningModules');
        $this->actingAs($student)->getJson("/api/learning-modules/{$version->id}/manifest?classId={$this->learningClass()->id}")
            ->assertForbidden();

        $this->actingAs($instructor)->getJson('/api/learning-modules')
            ->assertOk()
            ->assertJsonPath('learningModules.0.status', 'draft')
            ->assertJsonPath('learningModules.0.toolCount', 39);
        $response = $this->actingAs($instructor)->getJson("/api/learning-modules/{$version->id}/manifest")
            ->assertOk()
            ->assertJsonCount(15, 'module.tools')
            ->assertJsonCount(8, 'module.requiredStepIds')
            ->assertJsonMissingPath('module.activity.questions.0.answer');

        foreach ($response->json('module.tools') as $tool) {
            $this->assertNotEmpty($tool['name']);
            $this->assertNotEmpty($tool['purpose']);
            $this->assertNotEmpty($tool['handling']);
            $this->assertNotEmpty($tool['applicableStep']);
            $this->assertNotEmpty($tool['safety']);
            $this->assertNotEmpty($tool['sourceIds']);
            $this->assertNotEmpty($tool['licence']);
            $this->assertSame('awaiting_faculty_validation', $tool['validationStatus']);
        }
    }

    public function test_published_safety_media_uses_section_scoped_non_cacheable_urls(): void
    {
        $version = LearningModuleVersion::query()->where('version', '0.2.0')->firstOrFail();
        $this->submitReviewAndPublish($version);
        $classId = $this->learningClass()->id;
        $before = $version->fresh()->manifest;
        $response = $this->actingAs($this->student())->getJson("/api/learning-modules/{$version->id}/manifest?mediaPolicy=2&classId={$classId}")->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $assetUrl = collect($response->json('module.assetRecords'))->first()['url'];
        parse_str(parse_url($assetUrl, PHP_URL_QUERY), $query);
        $this->assertSame('2', $query['mediaPolicy']);
        $this->assertSame((string) $classId, $query['classId']);
        $asset = $this->get($assetUrl)->assertOk();
        $this->assertStringContainsString('no-store', $asset->headers->get('Cache-Control'));
        $this->getJson("/api/learning-module-versions/{$version->id}/assets/".$version->assets->first()->id)->assertUnprocessable();
        $other = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $this->actingAs($other)->getJson($assetUrl)->assertForbidden();
        $this->assertSame($before, $version->fresh()->manifest);
    }

    public function test_illustrated_draft_installs_once_and_keeps_assets_staff_only(): void
    {
        $version = LearningModuleVersion::query()->where('version', '0.2.0')->firstOrFail();
        $this->assertSame('draft', $version->status);
        $this->assertFalse($version->is_current);
        $this->assertCount(38, $version->assets);
        $this->assertSame(0, $version->reviews()->count());
        $manifest = $this->actingAs($this->instructor())->getJson("/api/learning-modules/{$version->id}/manifest")
            ->assertOk()->assertJsonCount(39, 'module.tools')->json('module');
        $usedKeys = collect($manifest['steps'])->flatMap(fn ($step) => $step['toolKeys'])->unique();
        foreach ($version->assets as $asset) {
            $this->assertTrue($asset->metadata['aiGenerated']);
            $this->assertSame('awaiting_faculty_validation', $asset->metadata['reviewStatus']);
            $this->assertTrue($usedKeys->contains($asset->asset_key));
            $url = $manifest['assetRecords'][$asset->asset_key]['url'];
            $this->actingAs($this->instructor())->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
            $this->actingAs($this->student())->get($url)->assertForbidden();
        }
        $this->seed(LearningModuleSeeder::class);
        $this->assertSame(38, $version->assets()->count());
        $this->assertSame(15, count($this->version()->manifest['tools']));
        $this->assertSame(2, LearningModuleVersion::query()->count());
    }

    public function test_independent_faculty_review_is_required_before_administrator_publication(): void
    {
        $admin = $this->admin();
        $instructor = $this->instructor();
        $version = $this->version();

        $this->actingAs($instructor)->postJson("/api/admin/learning-module-versions/{$version->id}/publish", ['classIds' => [$this->learningClass()->id]])
            ->assertForbidden();
        $this->actingAs($admin)->postJson("/api/staff/learning-module-versions/{$version->id}/submit")
            ->assertOk()->assertJsonPath('module.status', 'review');
        $this->actingAs($admin)->postJson("/api/staff/learning-module-versions/{$version->id}/review", $this->reviewPayload())
            ->assertUnprocessable()->assertJsonValidationErrors('reviewer');
        $this->actingAs($instructor)->postJson("/api/staff/learning-module-versions/{$version->id}/review", [
            ...$this->reviewPayload(),
            'localSopConfirmed' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('evidenceReference');
        $this->actingAs($instructor)->postJson("/api/staff/learning-module-versions/{$version->id}/review", $this->reviewPayload())
            ->assertOk()->assertJsonPath('module.latestReview.decision', 'approved');
        $this->actingAs($admin)->postJson("/api/admin/learning-module-versions/{$version->id}/publish", ['classIds' => [$this->learningClass()->id]])
            ->assertOk()->assertJsonPath('module.status', 'published');

        $this->assertDatabaseHas('class_module_assignments', [
            'class_id' => $this->learningClass()->id,
            'learning_module_version_id' => $version->id,
            'required' => true,
        ]);
        $this->actingAs($this->student())->getJson('/api/learning-modules')
            ->assertOk()->assertJsonPath('learningModules.0.version', '0.1.0');
    }

    public function test_completion_requires_every_step_and_one_activity_submission_but_not_a_passing_score(): void
    {
        $version = $this->publishCurrentVersion();
        $student = $this->student();
        $class = $this->learningClass();
        $package = SpeciesPackage::query()->findOrFail($class->assignments()->where('active', true)->value('species_package_id'));

        $this->actingAs($student)->getJson('/api/species')
            ->assertOk()->assertJsonPath('species.0.locked', true);
        $this->actingAs($student)->getJson("/api/species/tilapia/packages/{$package->id}/manifest?classId={$class->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('prerequisite');

        $stepIds = $version->manifest['requiredStepIds'];
        $partial = $this->progressPayload($class->id, array_slice($stepIds, 0, -1));
        $this->actingAs($student)->postJson("/api/learning-module-versions/{$version->id}/progress", $partial)
            ->assertCreated()->assertJsonPath('progress.completed', false);

        $attemptUuid = (string) Str::uuid();
        $complete = $this->progressPayload($class->id, $stepIds) + [
            'clientAttemptUuid' => $attemptUuid,
            'answers' => collect($version->manifest['activity']['questions'])->mapWithKeys(
                fn (array $question) => [$question['id'] => 'deliberately incorrect'],
            )->all(),
        ];
        $this->actingAs($student)->postJson("/api/learning-module-versions/{$version->id}/progress", $complete)
            ->assertCreated()
            ->assertJsonPath('progress.completed', true)
            ->assertJsonPath('attempt.score', 0);
        $this->actingAs($student)->postJson("/api/learning-module-versions/{$version->id}/progress", $complete)
            ->assertOk()->assertJsonPath('duplicate', true);
        $this->assertDatabaseCount('learning_module_attempts', 1);

        $this->actingAs($student)->getJson('/api/species')
            ->assertOk()->assertJsonPath('species.0.locked', false);
        $this->actingAs($student)->getJson("/api/species/tilapia/packages/{$package->id}/manifest?classId={$class->id}")
            ->assertOk();
    }

    public function test_documented_learner_and_section_exceptions_can_be_granted_and_revoked(): void
    {
        $version = $this->publishCurrentVersion();
        $class = $this->learningClass();
        $student = $this->student();
        $instructor = $this->instructor();

        $learnerOverride = $this->actingAs($instructor)->postJson(
            "/api/staff/classes/{$class->id}/learning-modules/{$version->learning_module_id}/overrides",
            ['studentId' => $student->id, 'reason' => 'Documented accommodation approved for this laboratory session.'],
        )->assertCreated()->json('override.id');
        $this->actingAs($student)->getJson('/api/species')->assertJsonPath('species.0.locked', false);
        $this->actingAs($instructor)->deleteJson("/api/staff/learning-module-overrides/{$learnerOverride}")
            ->assertOk()->assertJsonPath('override.revoked_at', fn ($value) => $value !== null);
        $this->actingAs($student)->getJson('/api/species')->assertJsonPath('species.0.locked', true);

        $this->actingAs($instructor)->postJson(
            "/api/staff/classes/{$class->id}/learning-modules/{$version->learning_module_id}/overrides",
            ['reason' => 'Documented section-wide exception approved for the current activity.'],
        )->assertCreated();
        $this->actingAs($student)->getJson('/api/species')->assertJsonPath('species.0.locked', false);
        $this->assertSame(2, LearningModuleOverride::query()->count());
    }

    public function test_pre_rollout_anatomy_attempt_receives_a_legacy_exception(): void
    {
        $student = $this->student();
        $class = $this->learningClass();
        $package = SpeciesPackage::query()->findOrFail($class->assignments()->where('active', true)->value('species_package_id'));
        LessonResult::query()->create([
            'user_id' => $student->id,
            'class_id' => $class->id,
            'species_package_id' => $package->id,
            'lesson_key' => $package->manifest['lessonKey'],
            'attempt_uuid' => (string) Str::uuid(),
            'package_version' => $package->version,
            'score' => 1,
            'total' => 3,
            'source' => 'online',
            'completed_at' => now(),
            'submitted_at' => now(),
            'synced_at' => now(),
        ]);

        $version = $this->publishCurrentVersion();
        $this->assertDatabaseHas('learning_module_overrides', [
            'class_id' => $class->id,
            'user_id' => $student->id,
            'learning_module_id' => $version->learning_module_id,
            'revoked_at' => null,
        ]);
        $this->actingAs($student)->getJson('/api/species')->assertJsonPath('species.0.locked', false);
    }

    public function test_started_learners_remain_pinned_when_a_new_version_is_published(): void
    {
        $first = $this->publishCurrentVersion();
        $student = $this->student();
        $class = $this->learningClass();
        $this->actingAs($student)->postJson("/api/learning-module-versions/{$first->id}/progress", $this->progressPayload(
            $class->id,
            [$first->manifest['requiredStepIds'][0]],
        ))->assertCreated();

        $newStudent = User::factory()->create(['role' => User::ROLE_STUDENT, 'status' => User::STATUS_ACTIVE]);
        ClassEnrollment::query()->create(['class_id' => $class->id, 'user_id' => $newStudent->id, 'status' => 'active']);
        $second = LearningModuleVersion::query()->where('version', '0.2.0')->firstOrFail();
        $this->submitReviewAndPublish($second);

        $this->assertSame($second->id, ClassModuleAssignment::query()->where('class_id', $class->id)->value('learning_module_version_id'));
        $this->actingAs($student)->getJson('/api/learning-modules')
            ->assertJsonPath('learningModules.0.version', '0.1.0')
            ->assertJsonPath('learningModules.0.pinned', true);
        $this->actingAs($newStudent)->getJson('/api/learning-modules')
            ->assertJsonPath('learningModules.0.version', '0.2.0')
            ->assertJsonPath('learningModules.0.pinned', false);
    }

    public function test_staff_media_prompt_studio_does_not_collect_an_api_key(): void
    {
        $version = $this->version();
        $payload = [
            'versionId' => $version->id,
            'toolKey' => 'scalpel',
            'mediaType' => 'illustration',
            'view' => 'three-quarter',
            'action' => 'proper-handling',
            'ppe' => 'gloved-hands',
            'attach' => true,
        ];
        $this->actingAs($this->student())->postJson('/api/staff/media-prompts/preview', $payload)->assertForbidden();
        $response = $this->actingAs($this->instructor())->postJson('/api/staff/media-prompts/preview', $payload)
            ->assertOk()->assertJsonPath('recordId', fn ($value) => is_int($value));
        $prompt = $response->json('prompt');
        $this->assertStringContainsString('faculty review', $prompt);
        $this->assertStringContainsString('personal names', $prompt);
        $this->assertStringNotContainsString('API key', $prompt);
        $this->assertDatabaseCount('media_prompt_records', 1);
        $this->assertNull(data_get(MediaPromptRecord::query()->firstOrFail()->options, 'apiKey'));
    }

    public function test_uploaded_images_are_reencoded_with_provenance_and_published_records_are_immutable(): void
    {
        $version = $this->version();
        $this->actingAs($this->instructor())->post("/api/staff/learning-module-versions/{$version->id}/assets", [
            'assetKey' => 'scalpel-side-view',
            'asset' => UploadedFile::fake()->image('candidate.jpg', 640, 480),
            'source' => 'Staff-created draft media candidate',
            'licence' => 'Internal educational review licence',
            'caption' => 'A scalpel shown above a dissecting tray for handling review.',
            'aiGenerated' => true,
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('asset.mime_type', 'image/webp')
            ->assertJsonPath('asset.metadata.metadataSanitization', 'server-reencoded');
        $asset = $version->assets()->where('asset_key', 'scalpel-side-view')->firstOrFail();
        $this->assertSame(64, strlen($asset->sha256));
        $this->assertTrue($asset->metadata['aiGenerated']);

        $published = $this->publishCurrentVersion()->fresh();
        $this->assertSame('faculty_validated', $asset->fresh()->metadata['reviewStatus']);
        $this->expectException(LogicException::class);
        $published->update(['manifest' => []]);
    }

    public function test_content_changes_after_approval_require_a_new_independent_review(): void
    {
        $version = $this->version();
        $admin = $this->admin();
        $instructor = $this->instructor();

        $this->actingAs($admin)->postJson("/api/staff/learning-module-versions/{$version->id}/submit")->assertOk();
        $this->actingAs($instructor)->postJson(
            "/api/staff/learning-module-versions/{$version->id}/review",
            $this->reviewPayload(),
        )->assertOk()->assertJsonPath('module.approvalCurrent', true);

        $this->actingAs($admin)->post("/api/staff/learning-module-versions/{$version->id}/assets", [
            'assetKey' => 'late-review-image',
            'asset' => UploadedFile::fake()->image('late-review.jpg', 640, 480),
            'source' => 'Staff-created draft media candidate',
            'licence' => 'Internal educational review licence',
            'caption' => 'A candidate illustration added after the first faculty approval.',
            'aiGenerated' => false,
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(LearningModuleVersion::STATUS_DRAFT, $version->fresh()->status);
        $this->actingAs($admin)->postJson("/api/admin/learning-module-versions/{$version->id}/publish", ['classIds' => [$this->learningClass()->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('module');

        $this->actingAs($admin)->postJson("/api/staff/learning-module-versions/{$version->id}/submit")->assertOk();
        $this->actingAs($instructor)->postJson(
            "/api/staff/learning-module-versions/{$version->id}/review",
            $this->reviewPayload(),
        )->assertOk()->assertJsonPath('module.approvalCurrent', true);
        $this->actingAs($admin)->postJson("/api/admin/learning-module-versions/{$version->id}/publish", ['classIds' => [$this->learningClass()->id]])
            ->assertOk()->assertJsonPath('module.status', 'published');
    }

    public function test_archived_assigned_version_remains_available_to_learners(): void
    {
        $version = $this->publishCurrentVersion();

        $this->actingAs($this->admin())->postJson("/api/admin/learning-module-versions/{$version->id}/archive")
            ->assertOk()->assertJsonPath('module.status', 'archived');
        $this->actingAs($this->student())->getJson('/api/learning-modules')
            ->assertOk()
            ->assertJsonCount(1, 'learningModules')
            ->assertJsonPath('learningModules.0.status', 'archived');
        $this->actingAs($this->student())->getJson(
            "/api/learning-modules/{$version->id}/manifest?classId={$this->learningClass()->id}",
        )->assertOk()->assertJsonPath('module.status', 'archived');
        $this->actingAs($this->student())->postJson(
            "/api/learning-module-versions/{$version->id}/progress",
            $this->progressPayload($this->learningClass()->id, [$version->manifest['requiredStepIds'][0]]),
        )->assertCreated();
    }

    private function publishCurrentVersion(): LearningModuleVersion
    {
        $version = $this->version();
        if ($version->status !== LearningModuleVersion::STATUS_PUBLISHED) {
            $this->submitReviewAndPublish($version);
        }

        return $version->fresh();
    }

    private function submitReviewAndPublish(LearningModuleVersion $version): void
    {
        $this->actingAs($this->admin())->postJson("/api/staff/learning-module-versions/{$version->id}/submit")->assertOk();
        $this->actingAs($this->instructor())->postJson("/api/staff/learning-module-versions/{$version->id}/review", $this->reviewPayload())->assertOk();
        $this->actingAs($this->admin())->postJson("/api/admin/learning-module-versions/{$version->id}/publish", ['classIds' => [$this->learningClass()->id]])->assertOk();
    }

    /** @return array<string, mixed> */
    private function reviewPayload(): array
    {
        return [
            'decision' => 'approved',
            'notes' => 'Reviewed the tool handling and safety statements against the controlled laboratory procedure.',
            'evidenceReference' => 'MB 304L controlled laboratory procedure, faculty review copy',
            'localSopConfirmed' => true,
        ];
    }

    /** @param array<int, string> $stepIds */
    private function progressPayload(int $classId, array $stepIds): array
    {
        return [
            'classId' => $classId,
            'completedStepIds' => $stepIds,
            'source' => 'online',
            'completedAt' => now()->toIso8601String(),
        ];
    }

    private function version(): LearningModuleVersion
    {
        return LearningModuleVersion::query()->where('version', '0.1.0')->firstOrFail();
    }

    private function learningClass(): LearningClass
    {
        return LearningClass::query()->where('code', 'FISHBIO-101')->firstOrFail();
    }

    private function student(): User
    {
        return User::query()->where('email', 'student@aquadissect.local')->firstOrFail();
    }

    private function instructor(): User
    {
        return User::query()->where('email', 'instructor@aquadissect.local')->firstOrFail();
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@aquadissect.local')->firstOrFail();
    }
}
