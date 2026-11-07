<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentStudy;
use App\Models\ClassEnrollment;
use App\Models\ClassInstructor;
use App\Models\LearningClass;
use App\Models\LessonResult;
use App\Models\SpeciesAssignment;
use App\Models\SpeciesPackage;
use App\Models\User;
use App\Services\CloneRefresher;
use App\Services\FishProfileSchema;
use App\Services\SpeciesPackageLifecycle;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClientFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['species-packages.visible_slugs' => []]);
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
            }
        }
        parent::tearDown();
    }

    private function actor(string $role): User
    {
        return User::where('role', $role)->firstOrFail();
    }

    private function package(): SpeciesPackage
    {
        return SpeciesPackage::where('status', 'published')->where('version', '1.1.0')->whereHas('species', fn ($q) => $q->where('slug', 'tilapia'))->firstOrFail();
    }

    private function form(): array
    {
        return [['id' => 'q1', 'prompt' => 'Synthetic question one', 'options' => ['Correct', 'Other'], 'answer' => 'Correct'], ['id' => 'q2', 'prompt' => 'Synthetic question two', 'options' => ['Correct', 'Other'], 'answer' => 'Correct']];
    }

    private function study(array $attributes = []): AssessmentStudy
    {
        $class = LearningClass::where('code', 'FISHBIO-101')->firstOrFail();
        ClassInstructor::firstOrCreate(['class_id' => $class->id, 'user_id' => $this->actor('instructor')->id]);

        return AssessmentStudy::create(['class_id' => $class->id, 'species_package_id' => $this->package()->id, 'author_id' => $this->actor('instructor')->id, 'title' => 'Synthetic instrument', 'instrument_version' => 'synthetic-1', 'pre_questions' => $this->form(), 'post_questions' => $this->form(), 'status' => 'draft', ...$attributes]);
    }

    public function test_profile_revision_preserves_existing_packages_accounts_enrollments_and_results(): void
    {
        $p = $this->package();
        $old = $p->manifest;
        $before = [User::count(), DB::table('class_enrollments')->count(), LessonResult::count(), SpeciesPackage::count()];
        $hash = hash_file('sha256', storage_path('app/private/'.$p->archive_path.'/manifest.json'));
        $version = '8.2.'.random_int(1000, 9999);
        $this->paths[] = storage_path('app/private/species-packages/'.$p->species->slug.'/'.$version);
        $profile = ['description' => 'Synthetic reviewed description', 'mouthPosition' => 'Synthetic morphology', 'localNames' => [['name' => 'Test local name', 'language' => 'Test language', 'locality' => 'Synthetic locality', 'source' => 'Synthetic source']]];
        $res = $this->actingAs($this->actor('admin'))->postJson('/api/staff/species-packages/'.$p->id.'/revision', ['version' => $version, 'profile' => json_encode($profile), 'calibrations' => '{}'])->assertCreated()->assertJsonPath('package.status', 'draft');
        $new = SpeciesPackage::findOrFail($res->json('package.id'));
        $this->assertSame($profile, $new->manifest['speciesProfile']);
        $this->assertSame($old, $p->fresh()->manifest);
        $this->assertSame($hash, hash_file('sha256', storage_path('app/private/'.$p->archive_path.'/manifest.json')));
        $this->assertSame($before, [User::count(), DB::table('class_enrollments')->count(), LessonResult::count(), SpeciesPackage::count() - 1]);
        $this->actingAs($this->actor('student'))->postJson('/api/staff/species-packages/'.$p->id.'/revision', ['version' => '9.0.0', 'profile' => '{}'])->assertForbidden();
        $this->actingAs($this->actor('student'))->getJson('/api/species/'.$p->species->slug.'/packages/'.$new->id.'/manifest')->assertForbidden();
        app(SpeciesPackageLifecycle::class)->submit($new, $this->actor('admin'));
        $this->actingAs($this->actor('admin'))->postJson('/api/admin/species-packages/'.$new->id.'/publish')->assertUnprocessable();
    }

    public function test_profile_rejects_unknown_private_fields_and_bad_calibrations(): void
    {
        $p = $this->package();
        $this->actingAs($this->actor('admin'))->postJson('/api/staff/species-packages/'.$p->id.'/revision', ['version' => '8.3.1', 'profile' => json_encode(['privateNotes' => 'Do not retain'])])->assertUnprocessable();
        $this->actingAs($this->actor('admin'))->post('/api/staff/species-packages/'.$p->id.'/revision', [
            'version'=>'8.3.2', 'profile'=>'{}', 'video'=>\Illuminate\Http\UploadedFile::fake()->image('unexpected.png'),
        ], ['Accept'=>'application/json'])->assertUnprocessable()->assertJsonValidationErrors('video');
        $manifest = $p->manifest;
        $manifest['calibrations'] = ['natural' => ['a' => ['x' => 0, 'y' => 0], 'b' => ['x' => 0, 'y' => 0], 'lengthMm' => 100, 'width' => 100, 'height' => 100, 'imageSha256' => str_repeat('0', 64), 'source' => 'Synthetic', 'basis' => 'photograph', 'scope' => 'body']];
        try {
            FishProfileSchema::validate($manifest);
            $this->fail('Bad calibration accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('calibrations', $e->errors());
        }
        $this->assertDatabaseMissing('species_packages', ['version' => '8.3.1']);
    }

    public function test_prepost_stays_disabled_until_independent_approval_and_admin_activation(): void
    {
        $s = $this->study();
        $student = $this->actor('student');
        $teacher = $this->actor('instructor');
        $admin = $this->actor('admin');
        $this->actingAs($student)->getJson('/api/paired-assessments')->assertJsonCount(0, 'studies');
        $this->actingAs($student)->getJson('/api/paired-assessments/'.$s->id.'/pre/questions')->assertForbidden();
        $this->actingAs($teacher)->postJson('/api/paired-assessments/'.$s->id.'/actions/approve', ['evidence' => 'Synthetic validation evidence', 'formsValidated' => true, 'policyConfirmed' => true])->assertUnprocessable();
        $this->actingAs($admin)->postJson('/api/paired-assessments/'.$s->id.'/actions/activate')->assertUnprocessable();
        $this->actingAs($admin)->postJson('/api/paired-assessments/'.$s->id.'/actions/approve', ['evidence' => 'Synthetic validation evidence', 'formsValidated' => true, 'policyConfirmed' => true])->assertOk();
        $this->actingAs($student)->getJson('/api/paired-assessments')->assertJsonCount(0, 'studies');
        $this->getJson('/api/paired-assessments/'.$s->id.'/pre/questions')->assertForbidden();
        $this->actingAs($teacher)->postJson('/api/paired-assessments/'.$s->id.'/actions/activate')->assertForbidden();
        $this->actingAs($admin)->postJson('/api/paired-assessments/'.$s->id.'/actions/activate')->assertOk();
        $this->actingAs($student)->getJson('/api/paired-assessments')->assertJsonPath('studies.0.id', $s->id);
        $this->actingAs($student)->getJson('/api/paired-assessments/'.$s->id.'/definition')->assertForbidden();
        $q = $this->actingAs($student)->getJson('/api/paired-assessments/'.$s->id.'/pre/questions')->assertOk();
        $this->assertArrayNotHasKey('answer', $q->json('questions.0'));
        $outsider = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $this->actingAs($outsider)->getJson('/api/paired-assessments/'.$s->id.'/pre/questions')->assertForbidden();
    }

    private function activate(AssessmentStudy $s): void
    {
        $admin = $this->actor('admin');
        $this->actingAs($admin)->postJson('/api/paired-assessments/'.$s->id.'/actions/approve', ['evidence' => 'Synthetic faculty evidence', 'formsValidated' => true, 'policyConfirmed' => true])->assertOk();
        $this->actingAs($admin)->postJson('/api/paired-assessments/'.$s->id.'/actions/activate')->assertOk();
    }

    public function test_multi_section_lesson_completion_unlocks_only_the_selected_sections_posttest(): void
    {
        $first = $this->study();
        $student = $this->actor('student');
        $package = $this->package();
        $originalClass = LearningClass::findOrFail($first->class_id);
        $secondClass = $originalClass->replicate();
        $secondClass->fill(['code' => 'QA-SECOND-SECTION', 'name' => 'Synthetic second section', 'section_code' => 'QA-B'])->save();
        ClassEnrollment::create(['class_id' => $secondClass->id, 'user_id' => $student->id, 'status' => 'active']);
        SpeciesAssignment::create(['class_id' => $secondClass->id, 'species_id' => $package->species_id,
            'species_package_id' => $package->id, 'active' => true, 'assigned_by' => $this->actor('admin')->id, 'assigned_at' => now()]);
        $second = $this->study(['class_id' => $secondClass->id]);
        $this->activate($first);
        $this->activate($second);
        $before = [User::count(), ClassEnrollment::count(), SpeciesPackage::count()];

        foreach ([$first, $second] as $study) {
            $this->actingAs($student)->postJson('/api/paired-assessments/'.$study->id.'/pre/attempts', [
                'attemptUuid' => (string) Str::uuid(), 'answers' => ['q1' => 'Correct', 'q2' => 'Correct'],
            ])->assertCreated();
        }
        $manifest = app(\App\Services\SpeciesPackageContent::class)->manifest($package);
        $attempt = ['packageId' => $package->id, 'packageVersion' => $package->version,
            'clientAttemptUuid' => (string) Str::uuid(), 'source' => 'online', 'completedAt' => now()->subSecond()->toIso8601String(),
            'answers' => collect($manifest['contentData']['quiz']['questions'])->mapWithKeys(fn ($q) => [$q['id'] => $q['answer']])->all()];
        $url = '/api/lessons/'.$manifest['lessonKey'].'/attempts';
        $this->postJson($url, $attempt)->assertUnprocessable()->assertJsonValidationErrors('classId');
        $this->postJson($url, [...$attempt, 'classId' => $secondClass->id])->assertCreated()->assertJsonPath('result.class_id', $secondClass->id);
        $this->assertDatabaseHas('lesson_results', ['user_id' => $student->id, 'class_id' => $secondClass->id, 'species_package_id' => $package->id]);
        $this->postJson('/api/paired-assessments/'.$first->id.'/post/attempts', [
            'attemptUuid' => (string) Str::uuid(), 'answers' => ['q1' => 'Correct', 'q2' => 'Correct'],
        ])->assertUnprocessable();
        $this->postJson('/api/paired-assessments/'.$second->id.'/post/attempts', [
            'attemptUuid' => (string) Str::uuid(), 'answers' => ['q1' => 'Correct', 'q2' => 'Correct'],
        ])->assertCreated();
        $this->assertSame($before, [User::count(), ClassEnrollment::count(), SpeciesPackage::count()]);
    }

    public function test_scores_are_server_graded_paired_idempotent_and_history_is_retained(): void
    {
        $s = $this->study();
        $this->activate($s);
        $student = $this->actor('student');
        $this->actingAs($student)->postJson('/api/paired-assessments/'.$s->id.'/post/attempts', ['attemptUuid' => (string) Str::uuid(), 'answers' => ['q1' => 'Correct', 'q2' => 'Correct']])->assertUnprocessable();
        $pre = ['attemptUuid' => (string) Str::uuid(), 'answers' => ['q1' => 'Other', 'q2' => 'Other'], 'score' => 99];
        $this->actingAs($student)->postJson('/api/paired-assessments/'.$s->id.'/pre/attempts', $pre)->assertCreated()->assertJsonPath('result.score', 0);
        $this->postJson('/api/paired-assessments/'.$s->id.'/pre/attempts', $pre)->assertOk()->assertJsonPath('duplicate', true);
        $this->postJson('/api/paired-assessments/'.$s->id.'/pre/attempts', array_merge($pre, ['attemptUuid' => (string) Str::uuid()]))->assertConflict();
        $this->getJson('/api/paired-assessments')->assertJsonPath('studies.0.rows.0.pre.score', 0)->assertJsonPath('studies.0.rows.0.pointDifference', null);
        $legacy = LessonResult::create(['user_id' => $student->id, 'class_id' => $s->class_id, 'species_package_id' => $s->species_package_id, 'lesson_key' => 'synthetic-formative', 'package_version' => $this->package()->version, 'score' => 1, 'total' => 2, 'source' => 'online', 'completed_at' => now()]);
        $post = ['attemptUuid' => (string) Str::uuid(), 'answers' => ['q1' => 'Correct', 'q2' => 'Correct']];
        $this->postJson('/api/paired-assessments/'.$s->id.'/post/attempts', $post)->assertCreated()->assertJsonPath('result.score', 2);
        $this->getJson('/api/paired-assessments')->assertJsonPath('studies.0.rows.0.pointDifference', 2)->assertJsonPath('studies.0.rows.0.percentagePointDifference', 100);
        $this->assertSame(1, LessonResult::count());
        $this->assertSame(2, AssessmentAttempt::count());
        $this->assertDatabaseHas('clone_outbox', ['table_name' => 'assessment_attempts']);
        $this->get('/api/paired-assessments/'.$s->id.'/export')->assertOk();
        $attempt = AssessmentAttempt::where('phase', 'post')->firstOrFail();
        $this->actingAs($this->actor('instructor'))->postJson('/api/paired-assessments/'.$s->id.'/actions/void', ['attemptId' => $attempt->id, 'reason' => 'Synthetic correction reason'])->assertOk();
        $this->assertNotNull($attempt->fresh()->voided_at);
        $this->assertSame(2, $attempt->fresh()->score);
        $this->assertNotNull($legacy->fresh());
        $this->assertDatabaseHas('assessment_events', ['action' => 'void']);
        $this->actingAs($student)->getJson('/api/paired-assessments')->assertJsonPath('studies.0.rows.0.pointDifference', null);
    }

    public function test_creation_validates_forms_scope_and_equal_totals(): void
    {
        $s = $this->study();
        $body = ['classId' => $s->class_id, 'packageId' => $s->species_package_id, 'title' => 'Synthetic test', 'instrumentVersion' => 'new-1', 'preQuestions' => $this->form(), 'postQuestions' => $this->form()];
        $this->actingAs($this->actor('student'))->postJson('/api/paired-assessments', $body)->assertForbidden();
        $this->actingAs($this->actor('instructor'))->postJson('/api/paired-assessments', $body)->assertCreated()->assertJsonPath('study.status', 'draft');
        array_pop($body['postQuestions']);
        $this->postJson('/api/paired-assessments', $body)->assertUnprocessable();
        $body['postQuestions'] = $this->form();
        $body['preQuestions'][0]['answer'] = 'Outside choices';
        $this->postJson('/api/paired-assessments', $body)->assertUnprocessable();
    }

    public function test_keyed_forms_are_normalized_and_both_answer_keys_are_validated_on_import(): void
    {
        $study = $this->study();
        $form = $this->form();
        $keyed = ['first' => [...$form[0], 'options' => ['yes' => 'Correct', 'no' => 'Other']], 'second' => $form[1]];
        $body = ['classId' => $study->class_id, 'packageId' => $study->species_package_id,
            'title' => 'Keyed synthetic form', 'instrumentVersion' => 'new-keyed',
            'preQuestions' => $keyed, 'postQuestions' => $keyed];
        $teacher = $this->actor('instructor');
        $created = $this->actingAs($teacher)->postJson('/api/paired-assessments', $body)->assertCreated();
        $this->assertSame($form, AssessmentStudy::findOrFail($created->json('study.id'))->pre_questions);
        $this->assertSame($form, AssessmentStudy::findOrFail($created->json('study.id'))->post_questions);

        $body['preQuestions']['first']['answer'] = 'Not an option';
        $this->postJson('/api/paired-assessments', $body)->assertUnprocessable()->assertJsonValidationErrors('questions');
    }

    public function test_a_saved_keyed_form_can_be_reviewed_and_taken_without_rewriting_it(): void
    {
        $form = $this->form();
        $keyed = ['first' => [...$form[0], 'options' => ['yes' => 'Correct', 'no' => 'Other']], 'second' => $form[1]];
        $study = $this->study(['pre_questions' => $keyed, 'post_questions' => $keyed]);
        $path = '/api/paired-assessments/'.$study->id;
        $this->actingAs($this->actor('instructor'))->getJson($path.'/definition')->assertOk()
            ->assertJsonPath('preQuestions', $form)->assertJsonPath('postQuestions', $form);

        $this->activate($study);
        $this->actingAs($this->actor('student'))->getJson($path.'/pre/questions')->assertOk()
            ->assertJsonPath('questions.0.options', ['Correct', 'Other'])
            ->assertJsonMissingPath('questions.0.answer');
        $this->postJson($path.'/pre/attempts', [
            'attemptUuid' => (string) Str::uuid(), 'answers' => ['q1' => 'Correct', 'q2' => 'Other'],
        ])->assertCreated()->assertJsonPath('result.score', 1);
        $this->assertSame($keyed, $study->fresh()->pre_questions);
    }

    public function test_an_existing_ungradable_form_cannot_record_a_new_score(): void
    {
        $form = $this->form();
        $form[0]['answer'] = 'Not an option';
        $study = $this->study(['pre_questions' => $form]);
        $this->activate($study);
        $path = '/api/paired-assessments/'.$study->id.'/pre';

        $this->actingAs($this->actor('student'))->getJson($path.'/questions')->assertUnprocessable();
        $this->postJson($path.'/attempts', [
            'attemptUuid' => (string) Str::uuid(), 'answers' => ['q1' => 'Correct', 'q2' => 'Other'],
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('assessment_attempts', ['assessment_study_id' => $study->id]);
    }

    public function test_calibration_and_public_profile_follow_independent_package_review(): void
    {
        $p = $this->package();
        $version = '8.4.'.random_int(1000, 9999);
        $this->paths[] = storage_path('app/private/species-packages/'.$p->species->slug.'/'.$version);
        $manifest = $p->manifest;
        $size = getimagesize(storage_path('app/private/'.$p->archive_path.'/'.$manifest['assets']['natural']));
        $cal = ['natural' => ['a' => ['x' => 0.1, 'y' => 0.5], 'b' => ['x' => 0.9, 'y' => 0.5], 'lengthMm' => 200,
            'width' => $size[0], 'height' => $size[1], 'imageSha256' => $manifest['files'][$manifest['assets']['natural']],
            'source' => 'Synthetic reference for engineering checks only', 'basis' => 'illustrative_estimate', 'scope' => 'body']];
        $saved = $this->actingAs($this->actor('admin'))->postJson('/api/staff/species-packages/'.$p->id.'/revision',
            ['version' => $version, 'profile' => json_encode(['description' => 'Synthetic profile']), 'calibrations' => json_encode($cal)])->assertCreated();
        $revision = SpeciesPackage::findOrFail($saved->json('package.id'));
        $url = '/api/species/'.$p->species->slug.'/packages/'.$revision->id.'/manifest';
        $this->getJson($url)->assertOk()->assertJsonPath('package.enhancementsApproved', false);
        app(SpeciesPackageLifecycle::class)->submit($revision, $this->actor('admin'));
        $this->actingAs($this->actor('instructor'))->postJson('/api/staff/species-packages/'.$revision->id.'/review', [
            'decision' => 'approved', 'notes' => 'Synthetic content and calibration review', 'evidenceReference' => 'Synthetic faculty reference',
            'curriculumAlignmentConfirmed' => true, 'safetyReviewConfirmed' => true])->assertOk();
        $this->actingAs($this->actor('admin'))->postJson('/api/admin/species-packages/'.$revision->id.'/publish')->assertOk();
        $this->actingAs($this->actor('student'))->getJson($url)->assertOk()
            ->assertJsonPath('package.enhancementsApproved', true)->assertJsonPath('package.speciesProfile.description', 'Synthetic profile')
            ->assertJsonPath('package.calibrations.natural.basis', 'illustrative_estimate');
        $this->assertSame($manifest, $p->fresh()->manifest);
    }

    public function test_image_replacement_requires_hotspots_and_removes_source_metadata(): void
    {
        $p = $this->package();
        $version = '8.5.'.random_int(1000, 9999);
        $this->paths[] = storage_path('app/private/species-packages/'.$p->species->slug.'/'.$version);
        $sourceHash = hash_file('sha256', storage_path('app/private/'.$p->archive_path.'/'.$p->manifest['assets']['internal']));
        $image = UploadedFile::fake()->image('synthetic.png', 600, 400);
        $this->actingAs($this->actor('admin'))->post('/api/staff/species-packages/'.$p->id.'/revision',
            ['version' => $version, 'profile' => '{}', 'internal' => $image], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('anatomy');
        $anatomy = file_get_contents(storage_path('app/private/'.$p->archive_path.'/'.$p->manifest['content']['anatomy']));
        $res = $this->post('/api/staff/species-packages/'.$p->id.'/revision', ['version' => $version, 'profile' => '{}', 'internal' => $image, 'anatomy' => $anatomy], ['Accept' => 'application/json'])->assertCreated();
        $new = SpeciesPackage::findOrFail($res->json('package.id'));
        $this->assertSame('draft', $new->status);
        $this->assertTrue($new->manifest['assetProvenance']['internal']['personalMetadataRemoved']);
        $this->assertSame('assets/internal-revision.webp', $new->manifest['assets']['internal']);
        $this->assertSame($sourceHash, hash_file('sha256', storage_path('app/private/'.$p->archive_path.'/'.$p->manifest['assets']['internal'])));
    }

    public function test_negative_score_changes_and_disabled_history_remain_honest(): void
    {
        $s = $this->study();
        $this->activate($s);
        $u = $this->actor('student');
        foreach (['pre' => 2, 'post' => 0] as $phase => $score) {
            AssessmentAttempt::create([
                'assessment_study_id' => $s->id, 'user_id' => $u->id, 'phase' => $phase, 'attempt_uuid' => (string) Str::uuid(),
                'answers' => ['q1' => 'Synthetic', 'q2' => 'Synthetic'], 'score' => $score, 'total' => 2, 'submitted_at' => now()]);
        }
        $this->actingAs($u)->getJson('/api/paired-assessments')->assertJsonPath('studies.0.rows.0.pointDifference', -2)
            ->assertJsonPath('studies.0.rows.0.percentagePointDifference', -100);
        $this->actingAs($this->actor('instructor'))->postJson('/api/paired-assessments/'.$s->id.'/actions/disable', ['reason' => 'Synthetic closure reason'])->assertOk();
        $this->actingAs($u)->getJson('/api/paired-assessments')->assertJsonCount(1, 'studies')->assertJsonPath('studies.0.status', 'disabled');
        $this->getJson('/api/paired-assessments/'.$s->id.'/pre/questions')->assertForbidden();
    }

    public function test_new_assessment_graph_reaches_the_delayed_clone_and_attempts_cannot_be_overwritten(): void
    {
        config(['database.connections.sqlite_clone.database' => ':memory:', 'delayed-clone.connection' => 'sqlite_clone']);
        DB::purge('sqlite_clone');
        $this->artisan('migrate', ['--database' => 'sqlite_clone', '--force' => true])->assertSuccessful();
        $s = $this->study();
        $a = AssessmentAttempt::create(['assessment_study_id' => $s->id, 'user_id' => $this->actor('student')->id, 'phase' => 'pre',
            'attempt_uuid' => (string) Str::uuid(), 'answers' => ['q1' => 'Correct', 'q2' => 'Other'], 'score' => 1, 'total' => 2, 'submitted_at' => now()]);
        app(CloneRefresher::class)->refresh();
        $this->assertSame(1, DB::connection('sqlite_clone')->table('assessment_attempts')->where('id', $a->id)->value('score'));
        try {
            $a->update(['score' => 2]);
            $this->fail('Attempt overwritten');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('cannot be overwritten',$e->getMessage());
        }
        $this->assertSame(1,$a->fresh()->score);
        DB::purge('sqlite_clone');
    }
}
