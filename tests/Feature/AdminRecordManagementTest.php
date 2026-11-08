<?php

namespace Tests\Feature;

use App\Models\AdminRecordArchive;
use App\Models\AdminRecordBatch;
use App\Models\ClassEnrollment;
use App\Models\ClassModuleAssignment;
use App\Models\LearningClass;
use App\Models\LearningModuleReview;
use App\Models\LearningModuleVersion;
use App\Models\LessonResult;
use App\Models\RosterEvent;
use App\Models\SpeciesPackage;
use App\Models\User;
use App\Services\CloneRefresher;
use App\Services\DelayedCloneApplier;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRecordManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->withSession(['record_review_test' => true]);
        $this->withCredentials();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        $this->actingAs($this->admin());
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function preview(string $resource, string $action, array $ids): array
    {
        return $this->postJson('/api/admin/records/preview', ['resource' => $resource, 'action' => $action, 'ids' => $ids,
            'reason' => 'Manage unused synthetic test records.'])->assertOk()->json();
    }

    private function commit(array $review, string $action, ?string $uuid = null)
    {
        return $this->postJson('/api/admin/records/execute', ['token' => $review['token'], 'requestUuid' => $uuid ?? (string) Str::uuid(),
            'confirmation' => $action === 'delete' ? 'DELETE' : 'CONFIRM']);
    }

    private function unusedSection(): LearningClass
    {
        return LearningClass::create(['course_id' => LearningClass::firstOrFail()->course_id, 'code' => 'UNUSED-'.Str::uuid(),
            'name' => 'Unused synthetic section', 'academic_year' => '2027-2028', 'semester' => '1', 'section_code' => 'TEST', 'status' => 'active']);
    }

    public function test_only_active_administrators_can_open_or_use_bulk_actions(): void
    {
        foreach (['student', 'instructor', 'program_head'] as $role) {
            $actor = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($actor)->get('/record-management')->assertForbidden();
            $this->getJson('/api/admin/records?resource=students')->assertForbidden();
            $this->postJson('/api/admin/records/preview', [])->assertForbidden();
            $this->postJson('/api/admin/records/execute', [])->assertForbidden();
        }
        $this->actingAs($this->admin())->get('/record-management')->assertOk()->assertSee('Manage records');
    }

    public function test_student_listing_is_paginated_searchable_and_excludes_credentials_and_other_roles(): void
    {
        foreach (range(1, 12) as $number) {
            User::factory()->create(['role' => 'student', 'status' => 'pending', 'identity_number' => 'BULK-FIND-'.$number]);
        }
        $this->getJson('/api/admin/records?resource=students&q=BULK-FIND&perPage=10')
            ->assertOk()->assertJsonPath('total', 12)->assertJsonCount(10, 'rows')->assertJsonPath('lastPage', 2)
            ->assertJsonMissingPath('rows.0.password')->assertJsonMissingPath('rows.0.remember_token');
        $this->getJson('/api/admin/records?resource=students&q=BULK-FIND&perPage=10&page=2')
            ->assertOk()->assertJsonCount(2, 'rows');
        $this->postJson('/api/admin/records/preview', ['resource' => 'students', 'action' => 'archive', 'ids' => [$this->admin()->id], 'reason' => 'Synthetic administrative test.'])
            ->assertUnprocessable();
    }

    public function test_student_archive_and_restore_preserve_credentials_enrollments_and_learning_records(): void
    {
        $student = User::where('role', 'student')->firstOrFail();
        $pending = User::factory()->create(['role' => 'student', 'status' => 'pending']);
        $passwords = User::whereIn('id', [$student->id, $pending->id])->pluck('password', 'id')->all();
        $this->actingAs($student)->postJson('/api/lesson-results', ['score' => 2, 'total' => 3])->assertCreated();
        $enrollments = DB::table('class_enrollments')->get()->toJson();
        $results = DB::table('lesson_results')->get()->toJson();
        $this->actingAs($this->admin());
        $review = $this->preview('students', 'archive', [$student->id, $pending->id]);
        $this->assertTrue($review['allowed']);
        $this->commit($review, 'archive')->assertOk()->assertJsonPath('count', 2);
        $this->assertSame('archived', $student->fresh()->status);
        $this->assertSame($passwords, User::whereIn('id', [$student->id, $pending->id])->pluck('password', 'id')->all());
        $this->assertSame($enrollments, DB::table('class_enrollments')->get()->toJson());
        $this->assertSame($results, DB::table('lesson_results')->get()->toJson());
        $this->commit($this->preview('students', 'restore', [$student->id, $pending->id]), 'restore')->assertOk();
        $this->assertSame('active', $student->fresh()->status);
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame($passwords, User::whereIn('id', [$student->id, $pending->id])->pluck('password', 'id')->all());
        $this->assertSame($results, DB::table('lesson_results')->get()->toJson());
    }

    public function test_archived_students_cannot_use_old_sessions_password_change_or_password_login(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'password' => Hash::make('ExamplePassword88'), 'must_change_password' => true]);
        $this->commit($this->preview('students', 'archive', [$student->id]), 'archive')->assertOk();
        $this->actingAs($student->fresh())->getJson('/api/session')->assertUnauthorized();
        $this->actingAs($student->fresh())->postJson('/api/change-password', ['password' => 'DifferentPassword88', 'password_confirmation' => 'DifferentPassword88'])->assertUnauthorized();
        $this->postJson('/api/login', ['identifier' => $student->email, 'password' => 'ExamplePassword88'])->assertUnprocessable()->assertJsonValidationErrors('identifier');
        $this->assertTrue(Hash::check('ExamplePassword88', $student->fresh()->password));
    }

    public function test_mixed_delete_selection_is_atomic_and_linked_student_is_protected(): void
    {
        $unused = User::factory()->create(['role' => 'student', 'status' => 'pending']);
        $linked = User::factory()->create(['role' => 'student', 'status' => 'pending']);
        ClassEnrollment::create(['user_id' => $linked->id, 'class_id' => LearningClass::firstOrFail()->id, 'status' => 'active']);
        $review = $this->preview('students', 'delete', [$unused->id, $linked->id]);
        $this->assertFalse($review['allowed']);
        $this->commit($review, 'delete')->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $unused->id]);
        $this->assertDatabaseHas('users', ['id' => $linked->id]);
        $this->assertSame(0, AdminRecordBatch::count());
    }

    public function test_unused_student_deletion_requires_typed_confirmation_and_retains_setup_audit(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'pending']);
        $event = RosterEvent::create(['actor_id' => $this->admin()->id, 'user_id' => $student->id, 'action' => 'student_created']);
        $review = $this->preview('students', 'delete', [$student->id]);
        $this->assertTrue($review['allowed']);
        $this->postJson('/api/admin/records/execute', ['token' => $review['token'], 'requestUuid' => (string) Str::uuid(), 'confirmation' => 'CONFIRM'])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $student->id]);
        $this->commit($review, 'delete')->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $student->id]);
        $batch = AdminRecordBatch::firstOrFail();
        $this->assertSame($event->id, $batch->audit_details[0]['retainedSetupEvents'][0]['id']);
        $this->assertSame($student->id, $batch->audit_details[0]['retainedSetupEvents'][0]['user_id']);
        $this->assertArrayNotHasKey('password', $batch->audit_details[0]['before']);
    }

    public function test_section_archive_and_restore_retain_assignments_and_enrollments_and_empty_section_can_be_deleted(): void
    {
        $class = LearningClass::firstOrFail();
        $before = DB::table('class_enrollments')->get()->toJson();
        $assignments = DB::table('species_assignments')->get()->toJson();
        $this->commit($this->preview('sections', 'archive', [$class->id]), 'archive')->assertOk();
        $this->assertSame('archived', $class->fresh()->status);
        $this->commit($this->preview('sections', 'restore', [$class->id]), 'restore')->assertOk();
        $this->assertSame($before, DB::table('class_enrollments')->get()->toJson());
        $this->assertSame($assignments, DB::table('species_assignments')->get()->toJson());
        $this->assertFalse($this->preview('sections', 'delete', [$class->id])['allowed']);
        $unused = $this->unusedSection();
        RosterEvent::create(['actor_id' => $this->admin()->id, 'class_id' => $unused->id, 'action' => 'offering_created']);
        $this->commit($this->preview('sections', 'delete', [$unused->id]), 'delete')->assertOk();
        $this->assertDatabaseMissing('classes', ['id' => $unused->id]);
    }

    public function test_enrollment_with_even_unattributed_learning_history_cannot_be_deleted(): void
    {
        $student = User::where('role', 'student')->firstOrFail();
        $enrollment = ClassEnrollment::where('user_id', $student->id)->firstOrFail();
        $this->actingAs($student)->postJson('/api/lesson-results', ['score' => 0, 'total' => 3])->assertCreated();
        $this->actingAs($this->admin());
        $this->assertFalse($this->preview('enrollments', 'delete', [$enrollment->id])['allowed']);
        $this->commit($this->preview('enrollments', 'archive', [$enrollment->id]), 'archive')->assertOk();
        $this->assertSame(1, LessonResult::where('user_id', $student->id)->count());
        $this->commit($this->preview('enrollments', 'restore', [$enrollment->id]), 'restore')->assertOk();
        $this->assertSame('active', $enrollment->fresh()->status);
    }

    public function test_unused_enrollment_can_be_removed_without_deleting_the_student_or_section(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'pending']);
        $class = LearningClass::firstOrFail();
        $enrollment = ClassEnrollment::create(['user_id' => $student->id, 'class_id' => $class->id, 'status' => 'active']);
        $this->commit($this->preview('enrollments', 'delete', [$enrollment->id]), 'delete')->assertOk();
        $this->assertDatabaseMissing('class_enrollments', ['id' => $enrollment->id]);
        $this->assertDatabaseHas('users', ['id' => $student->id]);
        $this->assertDatabaseHas('classes', ['id' => $class->id]);
    }

    public function test_changed_rows_or_newly_linked_enrollments_invalidate_the_preview(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'pending']);
        $review = $this->preview('students', 'delete', [$student->id]);
        ClassEnrollment::create(['user_id' => $student->id, 'class_id' => LearningClass::firstOrFail()->id, 'status' => 'active']);
        $this->commit($review, 'delete')->assertConflict();
        $review = $this->preview('students', 'archive', [$student->id]);
        $student->update(['first_name' => 'Changed synthetic name']);
        $this->commit($review, 'archive')->assertConflict();
        $this->assertSame('pending', $student->fresh()->status);
        $this->assertSame(0, AdminRecordBatch::count());
    }

    public function test_expired_or_other_actor_previews_fail_and_retries_are_idempotent(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'pending']);
        $review = $this->preview('students', 'archive', [$student->id]);
        $this->travel(6)->minutes();
        $this->commit($review, 'archive')->assertConflict();
        $review = $this->preview('students', 'archive', [$student->id]);
        $other = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($other);
        $this->commit($review, 'archive')->assertForbidden();
        $this->actingAs($this->admin());
        $uuid = (string) Str::uuid();
        $this->commit($review, 'archive', $uuid)->assertOk()->assertJsonPath('replayed', false);
        $this->travel(6)->minutes();
        $this->commit($review, 'archive', $uuid)->assertOk()->assertJsonPath('replayed', true);
        $this->assertSame(1, AdminRecordBatch::count());
        $this->assertSame(1, $student->fresh()->password_revision);
    }

    public function test_failure_during_the_second_record_rolls_back_the_entire_batch_and_audit(): void
    {
        $students = User::factory()->count(2)->create(['role' => 'student', 'status' => 'pending']);
        $review = $this->preview('students', 'delete', $students->modelKeys());
        $target = $students->last()->id;
        Event::listen('eloquent.deleting: '.User::class, function (User $user) use ($target): void {
            if ($user->id === $target) {
                throw new \RuntimeException('Synthetic transaction failure');
            }
        });
        try {
            $this->commit($review, 'delete')->assertStatus(500);
            $this->assertSame(2, User::whereKey($students->modelKeys())->count());
            $this->assertSame(0, AdminRecordBatch::count());
        } finally {
            Event::forget('eloquent.deleting: '.User::class);
        }
    }

    public function test_archiving_an_unapproved_safety_version_cannot_expose_it_to_students(): void
    {
        $version = LearningModuleVersion::firstOrFail();
        $class = LearningClass::firstOrFail();
        ClassModuleAssignment::create(['class_id' => $class->id, 'learning_module_id' => $version->learning_module_id,
            'learning_module_version_id' => $version->id, 'required' => true, 'active' => true, 'assigned_at' => now()]);
        $this->commit($this->preview('safety-versions', 'archive', [$version->id]), 'archive')->assertOk();
        $this->assertSame('withdrawn', $version->fresh()->status);
        $student = User::where('role', 'student')->firstOrFail();
        $this->actingAs($student)->getJson('/api/learning-modules')->assertOk()->assertJsonCount(0, 'learningModules');
        $this->getJson('/api/learning-modules/'.$version->id.'/manifest?classId='.$class->id)->assertForbidden();
        $this->actingAs($this->admin());
        $this->commit($this->preview('safety-versions', 'restore', [$version->id]), 'restore')->assertOk();
        $this->assertSame('draft', $version->fresh()->status);
    }

    public function test_restoring_published_safety_content_requires_a_new_review_and_keeps_published_content_immutable(): void
    {
        $this->freezeTime();
        $original = LearningModuleVersion::firstOrFail();
        $version = LearningModuleVersion::create(['learning_module_id' => $original->learning_module_id, 'version' => '8.0.0',
            'status' => 'review', 'manifest' => ['tools' => []], 'is_current' => false, 'imported_by' => $this->admin()->id]);
        $reviewer = User::where('role', 'instructor')->firstOrFail();
        $makeReview = fn () => LearningModuleReview::create(['learning_module_version_id' => $version->id, 'reviewer_id' => $reviewer->id,
            'reviewer_role' => 'Faculty Reviewer', 'decision' => 'approved', 'notes' => 'Synthetic engineering review only.',
            'evidence_reference' => 'Synthetic review evidence', 'local_sop_confirmed' => true]);
        $makeReview();
        $version->update(['status' => 'published', 'published_at' => now()]);
        $date = $version->fresh()->published_at->toIso8601String();
        $this->commit($this->preview('safety-versions', 'archive', [$version->id]), 'archive')->assertOk();
        $this->commit($this->preview('safety-versions', 'restore', [$version->id]), 'restore')->assertOk();
        $this->postJson('/api/admin/learning-module-versions/'.$version->id.'/publish', ['classIds' => []])->assertUnprocessable()->assertJsonValidationErrors('review');
        $this->assertFalse(AdminRecordArchive::moduleReviewIsFresh($version->fresh(), $version->reviews()->first()));
        $makeReview();
        $this->postJson('/api/admin/learning-module-versions/'.$version->id.'/publish', ['classIds' => []])->assertOk();
        $this->assertSame($date, $version->fresh()->published_at->toIso8601String());
        $this->expectException(\LogicException::class);
        $version->fresh()->update(['manifest' => ['tools' => ['changed']]]);
    }

    public function test_unused_draft_package_deletion_preserves_import_trace_and_private_source_files(): void
    {
        $base = SpeciesPackage::whereNotNull('archive_path')->firstOrFail();
        $draft = $base->replicate();
        $draft->forceFill(['version' => '8.0.0', 'status' => 'draft', 'is_current' => false, 'published_at' => null,
            'archived_at' => null, 'reviewed_by' => null, 'review_round' => 0, 'archive_path' => 'species-packages/synthetic-unused/8.0.0'])->save();
        $asset = $base->assets()->firstOrFail()->replicate();
        $asset->species_package_id = $draft->id;
        $asset->save();
        $draft->events()->create(['user_id' => $this->admin()->id, 'event' => 'draft_imported', 'metadata' => ['source' => 'Synthetic draft']]);
        Storage::disk('local')->put($draft->archive_path.'/retained.txt', 'Synthetic private recovery material');
        $this->commit($this->preview('species-versions', 'delete', [$draft->id]), 'delete')->assertOk();
        $this->assertDatabaseMissing('species_packages', ['id' => $draft->id]);
        $this->assertDatabaseMissing('species_package_assets', ['id' => $asset->id]);
        $this->assertSame('draft_imported', AdminRecordBatch::firstOrFail()->audit_details[0]['retainedSetupEvents'][0]['event']);
        $this->assertTrue(Storage::disk('local')->exists($draft->archive_path.'/retained.txt'));
        $this->assertDatabaseHas('species_packages', ['id' => $base->id]);
    }

    public function test_archives_and_bulk_audit_replicate_after_the_existing_clone_delay(): void
    {
        config(['database.connections.sqlite_clone.database' => ':memory:']);
        DB::purge('sqlite_clone');
        Artisan::call('migrate', ['--database' => 'sqlite_clone', '--force' => true]);
        app(CloneRefresher::class)->refresh();
        $student = User::where('role', 'student')->firstOrFail();
        $this->commit($this->preview('students', 'archive', [$student->id]), 'archive')->assertOk();
        $this->assertSame('active', DB::connection('sqlite_clone')->table('users')->where('id', $student->id)->value('status'));
        $this->assertSame(0, DB::connection('sqlite_clone')->table('admin_record_batches')->count());
        $this->travel(13)->hours();
        $report = app(DelayedCloneApplier::class)->applyEligible(1000);
        $this->assertSame(0, $report['failed']);
        $this->assertSame('archived', DB::connection('sqlite_clone')->table('users')->where('id', $student->id)->value('status'));
        $this->assertSame(1, DB::connection('sqlite_clone')->table('admin_record_batches')->count());
        $this->assertSame(1, DB::connection('sqlite_clone')->table('admin_record_archives')->count());
    }

    public function test_a_review_from_another_browser_session_is_rejected(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'pending']);
        $review = $this->preview('students', 'archive', [$student->id]);
        $this->withCookie(config('session.cookie'), Str::random(40));
        $this->commit($review, 'archive')->assertForbidden();
        $this->assertSame('pending', $student->fresh()->status);
        $this->assertSame(0, AdminRecordBatch::count());
    }

    public function test_review_requests_do_not_consume_the_action_rate_limit(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'pending']);
        foreach (range(1, 12) as $attempt) {
            $review = $this->preview('students', 'archive', [$student->id]);
        }
        $this->commit($review, 'archive')->assertOk();
        $this->assertSame(1, AdminRecordBatch::count());
    }

    public function test_interleaved_previews_cannot_apply_twice_or_reuse_a_request_for_another_selection(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'pending']);
        $other = User::factory()->create(['role' => 'student', 'status' => 'pending']);
        $first = $this->preview('students', 'archive', [$student->id]);
        $second = $this->preview('students', 'archive', [$student->id]);
        $uuid = (string) Str::uuid();
        $this->commit($first, 'archive', $uuid)->assertOk();
        $this->commit($second, 'archive')->assertConflict();
        $different = $this->preview('students', 'archive', [$other->id]);
        $this->commit($different, 'archive', $uuid)->assertConflict();
        $this->assertSame('pending', $other->fresh()->status);
        $this->assertSame(1, AdminRecordBatch::count());
    }

    public function test_formal_assessment_attempts_protect_an_enrollment_from_permanent_deletion(): void
    {
        $student = User::where('role', 'student')->firstOrFail();
        $enrollment = ClassEnrollment::where('user_id', $student->id)->firstOrFail();
        $study = DB::table('assessment_studies')->insertGetId(['class_id' => $enrollment->class_id,
            'species_package_id' => SpeciesPackage::firstOrFail()->id, 'author_id' => $this->admin()->id,
            'title' => 'Synthetic assessment', 'instrument_version' => 'QA-only', 'pre_questions' => '[]', 'post_questions' => '[]', 'status' => 'draft']);
        DB::table('assessment_attempts')->insert(['assessment_study_id' => $study, 'user_id' => $student->id,
            'phase' => 'pre', 'attempt_uuid' => (string) Str::uuid(), 'answers' => '[]', 'score' => 0, 'total' => 1, 'submitted_at' => now()]);
        $review = $this->preview('enrollments', 'delete', [$enrollment->id]);
        $this->assertFalse($review['allowed']);
        $this->commit($review, 'delete')->assertUnprocessable();
        $this->assertSame(1, DB::table('assessment_attempts')->count());
        $this->assertDatabaseHas('class_enrollments', ['id' => $enrollment->id]);
    }

    public function test_reviewed_species_restore_is_not_publication_and_other_versions_are_unchanged(): void
    {
        $package = SpeciesPackage::whereNotNull('archive_path')->firstOrFail();
        $other = SpeciesPackage::where('id', '!=', $package->id)->firstOrFail();
        $otherState = $other->getAttributes();
        $package->forceFill(['status' => 'review', 'review_round' => 2, 'is_current' => false])->save();
        $this->assertFalse($this->preview('species-versions', 'delete', [$package->id])['allowed']);
        $this->commit($this->preview('species-versions', 'archive', [$package->id]), 'archive')->assertOk();
        $this->commit($this->preview('species-versions', 'restore', [$package->id]), 'restore')->assertOk();
        $this->assertSame('review', $package->fresh()->status);
        $this->assertSame(3, $package->fresh()->review_round);
        $this->assertFalse($package->fresh()->is_current);
        $this->assertSame($otherState, $other->fresh()->getAttributes());
    }
}
