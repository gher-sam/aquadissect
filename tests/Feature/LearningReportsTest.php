<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentStudy;
use App\Models\ClassEnrollment;
use App\Models\ClassInstructor;
use App\Models\ClassModuleAssignment;
use App\Models\LearningClass;
use App\Models\LearningModuleOverride;
use App\Models\LearningModuleProgress;
use App\Models\LearningModuleVersion;
use App\Models\LessonResult;
use App\Models\PracticeCompletion;
use App\Models\SpeciesAssignment;
use App\Models\SpeciesPackage;
use App\Models\User;
use App\Services\Reports\CompletionForecast;
use App\Services\Reports\LearningReport;
use App\Services\Reports\ReportExports;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use ZipArchive;

class LearningReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['species-packages.visible_slugs' => [], 'delayed-clone.enabled' => true]);
        $this->travelTo(now()->setDate(2026, 11, 16)->setTime(2, 0));
        $this->seed(DatabaseSeeder::class);
    }

    private function student(): User
    {
        return User::where('role', 'student')->firstOrFail();
    }

    private function staff(): User
    {
        return User::where('role', 'instructor')->firstOrFail();
    }

    private function section(): LearningClass
    {
        return LearningClass::where('code', 'FISHBIO-101')->firstOrFail();
    }

    private function package(): SpeciesPackage
    {
        return SpeciesPackage::findOrFail(SpeciesAssignment::where('class_id', $this->section()->id)->value('species_package_id'));
    }

    private function lessonResult(User $user, int $score = 0, ?int $classId = null, ?string $date = null): LessonResult
    {
        return LessonResult::create(['user_id' => $user->id, 'class_id' => $classId ?? $this->section()->id,
            'species_package_id' => $this->package()->id, 'package_version' => $this->package()->version,
            'lesson_key' => 'Synthetic formative lesson', 'score' => $score, 'total' => 5, 'completed_at' => $date ?? now()->subDay()]);
    }

    public function test_new_reports_enforce_role_scope_and_keep_legacy_reports_available(): void
    {
        $this->getJson('/api/reports/analytics')->assertUnauthorized();
        $this->lessonResult($this->student(), 0);
        $outsider = User::factory()->create(['role' => 'student', 'first_name' => 'Other', 'last_name' => 'Learner']);
        $otherClass = LearningClass::create(['name' => 'Other private section', 'code' => 'QA-OTHER', 'status' => 'active']);
        $this->lessonResult($outsider, 1, $otherClass->id);
        $this->actingAs($this->student())->getJson('/api/reports/analytics')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('summary.meanScore', 0);
        $this->getJson('/api/reports/records')->assertOk()->assertDontSee('Other Learner')->assertJsonCount(1, 'rows');
        $this->getJson('/api/reports/analytics?classId='.$otherClass->id)->assertForbidden();
        $this->getJson('/api/reports/records?learnerId='.$outsider->id)->assertForbidden();
        $this->actingAs($this->staff())->getJson('/api/reports/analytics?classId='.$otherClass->id)->assertForbidden();
        foreach (['admin', 'program_head'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->getJson('/api/reports/analytics?classId='.$otherClass->id)->assertOk();
        }
        $this->actingAs($this->student())->getJson('/api/reports')->assertOk()->assertJsonStructure(['rows', 'classes', 'activities']);
        $this->get('/api/reports?format=csv')->assertOk()->assertDownload('aquadissect-learning-records.csv');
    }

    public function test_latest_scores_unique_completion_and_missing_values_are_distinct(): void
    {
        $this->lessonResult($this->student(), 0, date: now()->subDays(4)->toDateTimeString());
        $this->lessonResult($this->student(), 4, date: now()->subDays(2)->toDateTimeString());
        $new = User::factory()->create(['role' => 'student']);
        ClassEnrollment::create(['class_id' => $this->section()->id, 'user_id' => $new->id, 'status' => 'active']);
        $r = $this->actingAs($this->staff())->getJson('/api/reports/analytics')->assertOk()
            ->assertJsonPath('summary.learners', 2)->assertJsonPath('summary.assignedItems', 2)
            ->assertJsonPath('summary.completedItems', 1)->assertJsonPath('summary.completionRate', 50)
            ->assertJsonPath('summary.meanScore', 80)->assertJsonPath('summary.scoreSamples', 1);
        $this->assertSame(2, $r->json('summary.recordCount'));
        $this->getJson('/api/reports/analytics?from=2026-10-01&to=2026-10-03')->assertOk()->assertJsonPath('summary.meanScore', null)->assertJsonPath('summary.scoreSamples', 0);
        $this->getJson('/api/reports/records')->assertOk()->assertJsonPath('rows.1.score', 0);
    }

    public function test_practice_rewards_remain_private_and_are_not_academic_results(): void
    {
        PracticeCompletion::create(['user_id' => $this->student()->id, 'class_id' => $this->section()->id,
            'species_package_id' => $this->package()->id, 'challenge_key' => 'v1:function:gills', 'points' => 10, 'completed_at' => now()->subDay()]);
        $this->actingAs($this->student())->getJson('/api/reports/analytics')->assertOk()->assertJsonPath('summary.practiceXp', 10)->assertJsonPath('summary.meanScore', null);
        $this->getJson('/api/reports/records?activity=practice')->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.xp', 10)->assertJsonPath('rows.0.score', null);
        $this->actingAs($this->staff())->getJson('/api/reports/analytics')->assertOk()->assertJsonPath('summary.practiceXp', null);
        $this->getJson('/api/reports/records')->assertOk()->assertJsonCount(0, 'rows');
        $this->getJson('/api/reports/analytics?activity=practice')->assertForbidden();
        $this->get('/api/reports/export?format=csv&activity=practice')->assertForbidden();
    }

    public function test_support_uses_real_deadlines_optional_thresholds_and_no_research_participation_flags(): void
    {
        $a = SpeciesAssignment::firstOrFail();
        $a->update(['assigned_at' => now()->subDays(25), 'due_at' => now()->subDay()]);
        ClassEnrollment::where('user_id', $this->student()->id)->update(['created_at' => now()->subDays(25)]);
        $r = $this->actingAs($this->staff())->getJson('/api/reports/analytics')->assertOk()->assertJsonPath('summary.needsReview', 1);
        $this->assertContains('Overdue assigned work', $r->json('support.0.reasons'));
        $this->assertContains('No saved learning activity for seven days', $r->json('support.0.reasons'));
        $this->lessonResult($this->student(), 0);
        $this->getJson('/api/reports/analytics')->assertOk()->assertJsonPath('summary.needsReview', 0);
        $this->getJson('/api/reports/analytics?scoreThreshold=60')->assertOk()->assertJsonPath('summary.needsReview', 1)
            ->assertJsonPath('support.0.latestScore', 0)->assertDontSee('Missing pretest');
    }

    public function test_safety_exceptions_satisfy_prerequisites_without_becoming_completions(): void
    {
        $v = LearningModuleVersion::firstOrFail();
        DB::table('learning_module_versions')->where('id', $v->id)->update(['status' => 'published']);
        $assignment = ClassModuleAssignment::create(['class_id' => $this->section()->id, 'learning_module_id' => $v->learning_module_id,
            'learning_module_version_id' => $v->id, 'active' => true, 'required' => false, 'assigned_at' => now()->subDays(20)]);
        $optional = $this->actingAs($this->staff())->getJson('/api/reports/analytics')->assertOk()->assertJsonPath('summary.assignedItems', 2);
        $this->assertSame(0, collect($optional->json('charts.completion'))->where('key', 'lesson:'.$this->package()->id)->first()['blocked']);
        $assignment->update(['required' => true]);
        $r = $this->actingAs($this->staff())->getJson('/api/reports/analytics')->assertOk();
        $this->assertSame(1, collect($r->json('charts.completion'))->where('key', 'lesson:'.$this->package()->id)->first()['blocked']);
        LearningModuleOverride::create(['class_id' => $this->section()->id, 'learning_module_id' => $v->learning_module_id, 'user_id' => $this->student()->id,
            'reason' => 'Synthetic exception for engineering QA.', 'granted_by' => $this->staff()->id, 'granted_at' => now()]);
        $r = $this->getJson('/api/reports/analytics')->assertOk()->assertJsonPath('summary.exceptionItems', 1)->assertJsonPath('summary.completedItems', 0);
        $this->assertSame(0, collect($r->json('charts.completion'))->where('key', 'lesson:'.$this->package()->id)->first()['blocked']);
        LearningModuleProgress::create(['user_id' => $this->student()->id, 'class_id' => $this->section()->id, 'learning_module_version_id' => $v->id,
            'completed_step_ids' => [], 'completed_at' => now()]);
        $this->getJson('/api/reports/analytics')->assertOk()->assertJsonPath('summary.exceptionItems', 0)->assertJsonPath('summary.completedItems', 1);
    }

    public function test_assessments_preserve_negative_differences_voids_and_instrument_versions(): void
    {
        $study = AssessmentStudy::create(['class_id' => $this->section()->id, 'species_package_id' => $this->package()->id,
            'author_id' => $this->staff()->id, 'title' => 'Synthetic paired form', 'instrument_version' => 'qa-1', 'status' => 'active', 'pre_questions' => [], 'post_questions' => []]);
        foreach (['pre' => 2, 'post' => 0] as $phase => $score) {
            AssessmentAttempt::create(['assessment_study_id' => $study->id,
                'user_id' => $this->student()->id, 'phase' => $phase, 'attempt_uuid' => Str::uuid(), 'answers' => [], 'score' => $score, 'total' => 2, 'submitted_at' => now()->subDays($phase === 'pre' ? 40 : 2)]);
        }
        $this->actingAs($this->staff())->getJson('/api/reports/analytics')->assertOk()->assertJsonPath('assessments.0.matchedPairs', 1)
            ->assertJsonPath('assessments.0.preMean', 100)->assertJsonPath('assessments.0.postMean', 0)->assertJsonPath('assessments.0.difference', -100);
        AssessmentAttempt::where('phase', 'post')->firstOrFail()->update(['voided_at' => now()]);
        $this->getJson('/api/reports/analytics')->assertOk()->assertJsonPath('assessments.0.matchedPairs', 0)
            ->assertJsonPath('assessments.0.postMean', null)->assertJsonPath('assessments.0.voidedAttempts', 1);
        $this->assertSame(2, AssessmentAttempt::count());
    }

    public function test_forecasts_require_history_and_stay_within_the_cohort(): void
    {
        $forecaster = app(CompletionForecast::class);
        $now = now('Asia/Manila');
        $items = [];
        foreach (range(0, 9) as $i) {
            $items[] = ['status' => $i < 5 ? 'Completed' : 'Not recorded', 'availableAt' => $now->copy()->subDays(40)->toIso8601String(),
                'completedAt' => $i < 5 ? $now->copy()->subDays(4 + $i)->toIso8601String() : null];
        }
        $forecast = $forecaster->calculate($items, 30, $now);
        $this->assertSame('available', $forecast['status']);
        $this->assertCount(31, $forecast['projection']);
        foreach ($forecast['projection'] as $p) {
            $this->assertLessThanOrEqual(10, $p['high']);
            $this->assertGreaterThanOrEqual(5, $p['low']);
            $this->assertLessThanOrEqual($p['high'], $p['expected']);
        }
        $items[0]['completedAt'] = null;
        $items[0]['status'] = 'Not recorded';
        $this->assertSame('insufficient', $forecaster->calculate($items, 14, $now)['status']);
        foreach ($items as &$item) {
            $item['availableAt'] = $now->copy()->subDays(5)->toIso8601String();
        } unset($item);
        $this->assertSame('insufficient', $forecaster->calculate($items, 14, $now)['status']);
    }

    public function test_record_pagination_and_filters_do_not_change_metric_totals(): void
    {
        foreach (range(1, 27) as $i) {
            $this->lessonResult($this->student(), $i % 6);
        }
        $this->actingAs($this->staff())->getJson('/api/reports/records?page=2&perPage=25')->assertOk()->assertJsonCount(2, 'rows')->assertJsonPath('total', 27);
        $this->getJson('/api/reports/analytics')->assertOk()->assertJsonPath('summary.recordCount', 27)->assertJsonPath('summary.scoreSamples', 1);
        $this->getJson('/api/reports/analytics?from=2026-11-12&to=2026-11-10')->assertUnprocessable();
        $this->getJson('/api/reports/analytics?scoreThreshold=101')->assertUnprocessable();
        $this->getJson('/api/reports/records?perPage=999')->assertUnprocessable();
    }

    public function test_archived_year_semester_and_unattributed_records_keep_their_scope(): void
    {
        $this->section()->update(['academic_year' => '2026-2027', 'semester' => '1']);
        $this->lessonResult($this->student(), 1);
        $archived = LearningClass::create(['code' => 'QA-PAST', 'name' => 'Retained synthetic section', 'status' => 'archived', 'academic_year' => '2025-2026', 'semester' => '2']);
        ClassInstructor::create(['class_id' => $archived->id, 'user_id' => $this->staff()->id]);
        $this->lessonResult($this->student(), 2, $archived->id);
        $unattributed = $this->lessonResult($this->student(), 0);
        $unattributed->update(['class_id' => null]);
        $this->actingAs($this->staff())->getJson('/api/reports/analytics')->assertOk()->assertJsonPath('summary.recordCount', 1)->assertJsonPath('summary.historicalCount', 0);
        $this->getJson('/api/reports/analytics?offeringStatus=all')->assertOk()->assertJsonPath('summary.recordCount', 2);
        $this->getJson('/api/reports/analytics?academicYear=2025-2026&semester=2&offeringStatus=all')->assertOk()
            ->assertJsonPath('summary.recordCount', 1)->assertJsonPath('summary.learners', 0)->assertJsonPath('summary.completionRate', null);
        $this->getJson('/api/reports/analytics?academicYear=2025-2026&semester=1&offeringStatus=all')->assertOk()->assertJsonPath('summary.recordCount', 0);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/reports/analytics')->assertOk()->assertJsonPath('summary.historicalCount', 1)->assertJsonPath('summary.scoreSamples', 1);
        $this->getJson('/api/reports/analytics?classId='.$this->section()->id)->assertOk()->assertJsonPath('summary.historicalCount', 0);
    }

    public function test_all_five_formats_include_branding_and_preserve_typed_data(): void
    {
        $student = $this->student();
        $student->forceFill(['first_name' => '=FORMULA', 'last_name' => 'Learner & <Record>', 'identity_number' => '00123'])->save();
        $this->lessonResult($student, 0);
        $r = app(LearningReport::class)->build($this->staff(), []);
        $directory = storage_path('framework/testing/report-export-'.Str::uuid());
        File::ensureDirectoryExists($directory);
        try {
            foreach (['csv', 'xml', 'xlsx', 'docx', 'pdf'] as $format) {
                $path = $directory.'/report.'.$format;
                app(ReportExports::class)->write($r, $format, 'detailed', $path);
                $this->assertGreaterThan(300, filesize($path));
                if ($format === 'csv') {
                    $text = file_get_contents($path);
                    $this->assertStringContainsString("'=FORMULA Learner", $text);
                    $this->assertStringContainsString('Bontoc Campus', $text);
                    $this->assertStringContainsString('End of report', $text);
                }
                if ($format === 'xml') {
                    $xml = simplexml_load_file($path);
                    $this->assertSame('Southern Leyte State University', (string) $xml->footer->institution);
                    $this->assertStringContainsString('00123', file_get_contents($path));
                }
                if ($format === 'pdf') {
                    $this->assertStringStartsWith('%PDF-', file_get_contents($path));
                }
                if (in_array($format, ['docx', 'xlsx'])) {
                    $zip = new ZipArchive;
                    $this->assertTrue($zip->open($path));
                    $this->assertStringContainsString('AquaDissect', $zip->getFromName('docProps/core.xml'));
                    if ($format === 'docx') {
                        $this->assertStringContainsString('Bontoc', $zip->getFromName('word/header1.xml'));
                        $this->assertStringNotContainsString('SLSU-QF-IN53', $zip->getFromName('word/footer1.xml'));
                        $this->assertNotFalse(simplexml_load_string($zip->getFromName('word/document.xml')));
                    } else {
                        $this->assertNotFalse($zip->getFromName('xl/charts/chart1.xml'));
                        $this->assertStringContainsString('headerFooter', $zip->getFromName('xl/worksheets/sheet1.xml'));
                    }
                    $zip->close();
                }
                if ($format === 'xlsx') {
                    $book = IOFactory::load($path);
                    $sheet = $book->getSheetByName('Section learning records');
                    $this->assertSame('00123', $sheet->getCell('C2')->getValue());
                    $this->assertSame('s', $sheet->getCell('B2')->getDataType());
                    $this->assertSame(0, $sheet->getCell('F2')->getValue());
                    $this->assertSame('n', $sheet->getCell('J2')->getDataType());
                    $this->assertStringContainsString('2026-11-15', $sheet->getCell('J2')->getFormattedValue());
                    $book->disconnectWorksheets();
                }
            }
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_generation_is_private_read_only_and_cleans_temporary_downloads(): void
    {
        $this->lessonResult($this->student(), 0);
        $before = DB::table('lesson_results')->get()->toJson();
        $this->actingAs($this->staff())->get('/api/reports/export?format=csv&layout=detailed')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertSame($before, DB::table('lesson_results')->get()->toJson());
        $this->assertSame(0, AssessmentAttempt::count());
        $this->assertSame(0, PracticeCompletion::count());
        $this->assertSame([], File::directories(storage_path('app/private/report-exports')));
        $this->get('/api/reports/export?format=invalid')->assertUnprocessable();
    }
}
