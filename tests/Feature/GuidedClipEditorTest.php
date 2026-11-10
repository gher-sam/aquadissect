<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentStudy;
use App\Models\LearningModuleProgress;
use App\Models\LessonResult;
use App\Models\SpeciesPackage;
use App\Models\User;
use App\Services\GuidedClipDrafts;
use App\Services\GuidedClipVideo;
use App\Services\SpeciesPackageLifecycle;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RabbitfishGuidedClipsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GuidedClipEditorTest extends TestCase
{
    use RefreshDatabase;

    private string $testStorage;

    private SpeciesPackage $package;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testStorage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'aqd-clips-'.Str::uuid();
        $this->app->useStoragePath($this->testStorage);
        File::ensureDirectoryExists(storage_path('app/private'));
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'filesystems.disks.local.root' => storage_path('app/private')]);
        Storage::forgetDisk('local');
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(DatabaseSeeder::class);
        $this->seed(RabbitfishGuidedClipsSeeder::class);
        $this->package = SpeciesPackage::whereHas('species', fn ($q) => $q->where('slug', 'rabbitfish'))->where('version', '1.3.0')->firstOrFail();
        $this->staff = User::where('role', 'admin')->firstOrFail();
    }

    protected function tearDown(): void
    {
        if (isset($this->testStorage) && str_starts_with($this->testStorage, sys_get_temp_dir().DIRECTORY_SEPARATOR.'aqd-clips-')) {
            File::deleteDirectory($this->testStorage);
        }
        parent::tearDown();
    }

    private function url(string $suffix = ''): string
    {
        return '/api/staff/species-packages/'.$this->package->id.'/clip-drafts'.$suffix;
    }

    private function start(): array
    {
        return $this->actingAs($this->staff)->postJson($this->url())->assertOk()->json();
    }

    private function fields(array $state): array
    {
        return array_map(fn ($s) => array_intersect_key($s, array_flip(['id', 'title', 'description', 'prompt', 'action', 'videoAsset'])), $state['steps']);
    }

    private function addSteps(array $state, int $count): array
    {
        $steps = $this->fields($state);
        for ($i = 0; $i < $count; $i++) {
            array_splice($steps, -1, 0, [['id' => 'n-'.str_replace('-', '', (string) Str::uuid()), 'title' => 'Faculty clip '.($i + 6), 'description' => 'Synthetic faculty instructions. Retain the tray position.', 'prompt' => 'Observe the clip.', 'action' => 'Continue', 'videoAsset' => null]]);
        }

        return $this->patchJson($this->url('/'.$state['draftId']), ['revision' => $state['revision'], 'steps' => $steps])->assertOk()->json();
    }

    private function fakeProcessor(): void
    {
        $source = resource_path('species-packs/rabbitfish/1.3.0/assets/clip-01.mp4');
        $this->mock(GuidedClipVideo::class, function ($mock) use ($source) {
            $mock->shouldReceive('prepare')->andReturnUsing(function ($file, $target) use ($source) {
                copy($source, $target);

                return ['durationSeconds' => 10.0, 'mimeType' => 'video/mp4', 'sizeBytes' => filesize($target), 'sha256' => hash_file('sha256', $target)];
            });
        });
    }

    private function upload(array $state, string $step, array $extra = [])
    {
        return $this->post($this->url('/'.$state['draftId'].'/assets'), [
            'revision' => $state['revision'], 'stepId' => $step, 'asset' => UploadedFile::fake()->create('clip.mp4', 2, 'video/mp4'),
            'caption' => 'Synthetic clip caption', 'source' => 'Faculty-supplied synthetic material', 'licence' => 'Synthetic testing only', 'aiGenerated' => '1', ...$extra,
        ], ['Accept' => 'application/json']);
    }

    public function test_owner_scoping_permissions_resume_expiry_and_cancellation(): void
    {
        $d = $this->start();
        $this->assertSame($d['draftId'], $this->start()['draftId']);
        $this->assertCount(5, array_filter($d['steps'], fn ($s) => $s['videoAsset'] !== null));
        $other = User::factory()->create(['first_name' => 'Faculty', 'last_name' => 'Reviewer', 'role' => 'instructor', 'status' => 'active']);
        $this->actingAs($other)->getJson($this->url('/'.$d['draftId']))->assertNotFound();
        foreach (['student', 'program_head'] as $role) {
            $u = User::factory()->create(['first_name' => 'Synthetic', 'last_name' => 'Account', 'role' => $role, 'status' => 'active']);
            $this->actingAs($u)->postJson($this->url())->assertForbidden();
            $this->getJson($this->url('/'.$d['draftId']))->assertForbidden();
        }
        $this->actingAs($this->staff)->deleteJson($this->url('/'.$d['draftId']))->assertOk();
        $this->getJson($this->url('/'.$d['draftId']))->assertStatus(410);
        $next = $this->start();
        $this->travel(25)->hours();
        $this->getJson($this->url('/'.$next['draftId']))->assertStatus(410);
        $this->assertGreaterThan(0, app(GuidedClipDrafts::class)->cleanup());
    }

    public function test_required_steps_order_and_fields_cannot_be_removed_or_forged(): void
    {
        $d = $this->start();
        $steps = $this->fields($d);
        $core = array_keys(array_filter($d['steps'], fn ($s) => ! $s['clipOnly']));
        unset($steps[$core[0]]);
        $this->patchJson($this->url('/'.$d['draftId']), ['revision' => 1, 'steps' => array_values($steps)])->assertUnprocessable();
        $steps = $this->fields($d);
        [$steps[$core[0]], $steps[$core[1]]] = [$steps[$core[1]], $steps[$core[0]]];
        $this->patchJson($this->url('/'.$d['draftId']), ['revision' => 1, 'steps' => $steps])->assertUnprocessable();
        $steps = $this->fields($d);
        $steps[] = array_shift($steps);
        $this->patchJson($this->url('/'.$d['draftId']), ['revision' => 1, 'steps' => $steps])->assertUnprocessable();
        $steps = $this->fields($d);
        $steps[0]['targets'] = [];
        $this->patchJson($this->url('/'.$d['draftId']), ['revision' => 1, 'steps' => $steps])->assertUnprocessable();
        $steps = $this->fields($d);
        $steps[0]['videoAsset'] = 'another-draft-clip';
        $this->patchJson($this->url('/'.$d['draftId']), ['revision' => 1, 'steps' => $steps])->assertUnprocessable();
        $this->patchJson($this->url('/'.$d['draftId']), ['revision' => 99, 'steps' => $this->fields($d)])->assertConflict();
    }

    public function test_full_72_clip_lesson_preserves_records_source_files_and_review_gates(): void
    {
        $this->fakeProcessor();
        $student = User::where('role', 'student')->firstOrFail();
        $class = DB::table('classes')->first();
        LessonResult::create(['user_id' => $student->id, 'class_id' => $class->id, 'species_package_id' => $this->package->id,
            'lesson_key' => 'rabbitfish', 'attempt_uuid' => Str::uuid(), 'package_version' => $this->package->version, 'score' => 3, 'total' => 5,
            'source' => 'online', 'completed_at' => now(), 'submitted_at' => now()]);
        $study = AssessmentStudy::create(['class_id' => $class->id, 'species_package_id' => $this->package->id, 'author_id' => $this->staff->id,
            'title' => 'Synthetic retained instrument', 'instrument_version' => 'qa1', 'pre_questions' => [], 'post_questions' => []]);
        AssessmentAttempt::create(['assessment_study_id' => $study->id, 'user_id' => $student->id, 'phase' => 'pre', 'attempt_uuid' => Str::uuid(),
            'answers' => ['qa' => 'a'], 'score' => 1, 'total' => 2, 'submitted_at' => now()]);
        LearningModuleProgress::create(['user_id' => $student->id, 'class_id' => $class->id,
            'learning_module_version_id' => DB::table('learning_module_versions')->value('id'), 'completed_step_ids' => ['qa-step'], 'source' => 'online']);
        $tables = ['users', 'classes', 'class_enrollments', 'lesson_results', 'assessment_studies', 'assessment_attempts', 'species_assignments', 'learning_module_progress'];
        $before = array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->toJson(), $tables);
        $fingerprint = app(SpeciesPackageLifecycle::class)->contentFingerprint($this->package);
        $d = $this->addSteps($this->start(), 67);
        foreach (array_filter($d['steps'], fn ($s) => str_starts_with($s['id'], 'n-')) as $step) {
            $d = $this->upload($d, $step['id'])->assertOk()->json();
        }
        $this->assertCount(72, array_filter($d['steps'], fn ($s) => $s['videoAsset'] !== null));
        $version = '8.72.1';
        $first = $this->postJson($this->url('/'.$d['draftId'].'/commit'), ['revision' => $d['revision'], 'version' => $version])->assertOk()->assertJsonPath('package.status', 'draft')->json();
        $this->postJson($this->url('/'.$d['draftId'].'/commit'), ['revision' => $d['revision'], 'version' => $version])->assertOk()->assertJsonPath('package.id', $first['package']['id']);
        $new = SpeciesPackage::findOrFail($first['package']['id']);
        $this->assertFalse($new->is_current);
        $this->assertSame(0, $new->reviews()->count());
        $this->assertSame($fingerprint, app(SpeciesPackageLifecycle::class)->contentFingerprint($this->package->fresh()));
        $this->assertSame($before, array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->toJson(), $tables));
        $lesson = json_decode(file_get_contents(storage_path('app/private/'.$new->archive_path.'/content/lesson.json')), true);
        $this->assertCount(count($lesson['steps']), $lesson['stepDepths']);
        $this->assertSame('quiz', end($lesson['steps'])['kind']);
        $this->assertSame($lesson['stepDepths'][count($lesson['steps']) - 69], $lesson['stepDepths'][count($lesson['steps']) - 2]);
        $this->assertSame(1, $new->events()->where('event', 'guided_clips_saved')->count());
        $new->events()->where('event', 'guided_clips_saved')->delete();
        $this->postJson($this->url('/'.$d['draftId'].'/commit'), ['revision' => $d['revision'], 'version' => $version])->assertOk()->assertJsonPath('package.id', $new->id);
        $this->assertSame(1, $new->events()->where('event', 'guided_clips_saved')->count());
        $this->actingAs(User::where('role', 'student')->firstOrFail())->getJson('/api/species/rabbitfish/packages/'.$new->id.'/manifest')->assertForbidden();
        $this->actingAs($this->staff);
        app(SpeciesPackageLifecycle::class)->submit($new, $this->staff);
        $this->postJson('/api/staff/species-packages/'.$new->id.'/review', ['decision' => 'approved', 'notes' => 'Synthetic review notes only', 'evidenceReference' => 'Synthetic record', 'curriculumAlignmentConfirmed' => true, 'safetyReviewConfirmed' => true])->assertUnprocessable();
        $this->postJson('/api/admin/species-packages/'.$new->id.'/publish')->assertUnprocessable();
    }

    public function test_video_preview_ranges_replacement_failure_and_missing_metadata(): void
    {
        $this->fakeProcessor();
        $d = $this->start();
        $this->upload($d, $d['steps'][0]['id'], ['caption' => ''])->assertUnprocessable();
        $this->upload($d, $d['steps'][0]['id'], ['aiGenerated' => ''])->assertUnprocessable();
        $d = $this->upload($d, $d['steps'][0]['id'])->assertOk()->json();
        $key = $d['steps'][0]['videoAsset'];
        $url = $this->url('/'.$d['draftId'].'/assets/'.$key);
        $this->get($url, ['Range' => 'bytes=0-99'])->assertStatus(206)->assertHeader('Content-Type', 'video/mp4');
        $this->app->forgetInstance(GuidedClipVideo::class);
        $this->mock(GuidedClipVideo::class, fn ($m) => $m->shouldReceive('prepare')->andThrow(ValidationException::withMessages(['asset' => 'Synthetic processing failure'])));
        $this->upload($d, $d['steps'][0]['id'])->assertUnprocessable();
        $this->getJson($this->url('/'.$d['draftId']))->assertJsonPath('steps.0.videoAsset', $key);
        $this->get($url)->assertOk();
        $this->deleteJson($this->url('/'.$d['draftId']))->assertOk();
        $this->get($url, ['Accept' => 'application/json'])->assertStatus(410);
    }

    public function test_failed_commits_keep_draft_and_pending_clips_need_a_video(): void
    {
        $d = $this->addSteps($this->start(), 1);
        $this->postJson($this->url('/'.$d['draftId'].'/commit'), ['revision' => $d['revision'], 'version' => '8.2.1'])->assertUnprocessable();
        $this->getJson($this->url('/'.$d['draftId']))->assertOk();
        $this->fakeProcessor();
        $d = $this->upload($d, $d['steps'][count($d['steps']) - 2]['id'])->assertOk()->json();
        $this->postJson($this->url('/'.$d['draftId'].'/commit'), ['revision' => $d['revision'], 'version' => '1.3.0'])->assertUnprocessable();
        $this->getJson($this->url('/'.$d['draftId']))->assertOk();
        $path = storage_path('app/private/'.$this->package->archive_path.'/content/lesson.json');
        file_put_contents($path, 'changed');
        $this->postJson($this->url('/'.$d['draftId'].'/commit'), ['revision' => $d['revision'], 'version' => '8.2.2'])->assertUnprocessable();
        $this->getJson($this->url('/'.$d['draftId']))->assertOk();
    }
}
