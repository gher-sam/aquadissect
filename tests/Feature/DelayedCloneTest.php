<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\ClassEnrollment;
use App\Models\ClassInstructor;
use App\Models\ClassModuleAssignment;
use App\Models\CloneOutbox;
use App\Models\ExternalIdentity;
use App\Models\LearningClass;
use App\Models\LearningModule;
use App\Models\LearningModuleAsset;
use App\Models\LearningModuleAttempt;
use App\Models\LearningModuleOverride;
use App\Models\LearningModuleProgress;
use App\Models\LearningModuleReview;
use App\Models\LearningModuleVersion;
use App\Models\LessonResult;
use App\Models\MediaPromptRecord;
use App\Models\Species;
use App\Models\SpeciesAssignment;
use App\Models\SpeciesPackage;
use App\Models\SpeciesPackageAsset;
use App\Models\SpeciesPackageEvent;
use App\Models\SpeciesPackageReview;
use App\Models\User;
use App\Models\WorkspaceNotification;
use App\Services\CloneRefresher;
use App\Services\DelayedCloneApplier;
use App\Services\DelayedCloneWrite;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class DelayedCloneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.sqlite_clone.database', ':memory:');
        config()->set('delayed-clone.connection', 'sqlite_clone');
        config()->set('delayed-clone.delay_hours', 12);
        DB::purge('sqlite_clone');
        Artisan::call('migrate:fresh', [
            '--database' => 'sqlite_clone',
            '--force' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('sqlite_clone');

        parent::tearDown();
    }

    public function test_google_identity_and_nullable_password_replay_after_twelve_hours_and_refresh(): void
    {
        Carbon::setTestNow('2026-10-02 00:00:00');
        $user = app(DelayedCloneWrite::class)->transaction(fn () => User::factory()->create(['password' => null]));
        $identity = app(DelayedCloneWrite::class)->transaction(fn () => ExternalIdentity::create([
            'user_id' => $user->id, 'provider' => 'google', 'subject' => 'clone-subject',
            'verified_email' => $user->email, 'verified_at' => now(),
        ]));
        $applier = app(DelayedCloneApplier::class);
        $this->assertSame(0, $applier->applyEligible()['applied']);
        $this->assertFalse(DB::connection('sqlite_clone')->table('external_identities')->exists());
        Carbon::setTestNow('2026-10-02 12:00:00');
        $this->assertSame(0, $applier->applyEligible(1000)['failed']);
        $this->assertSame('clone-subject', DB::connection('sqlite_clone')->table('external_identities')->find($identity->id)->subject);
        $this->assertNull(DB::connection('sqlite_clone')->table('users')->find($user->id)->password);
        app(DelayedCloneWrite::class)->transaction(fn () => $identity->update(['verified_email' => 'new@example.test']));
        Carbon::setTestNow('2026-10-03 00:00:00');
        $this->assertSame(0, $applier->applyEligible(1000)['failed']);
        $this->assertSame('new@example.test', DB::connection('sqlite_clone')->table('external_identities')->find($identity->id)->verified_email);
        $counts = app(CloneRefresher::class)->refresh();
        $this->assertSame(1, $counts['external_identities']);
        $this->assertNull(DB::connection('sqlite_clone')->table('users')->find($user->id)->password);
    }

    public function test_calendar_and_notification_records_replay_in_order(): void
    {
        Carbon::setTestNow('2026-09-27 00:00:00');
        $this->seed(DatabaseSeeder::class);
        app(CloneRefresher::class)->refresh();
        $class = LearningClass::firstOrFail();
        $staff = User::where('role', 'instructor')->firstOrFail();
        $event = app(DelayedCloneWrite::class)->transaction(fn () => CalendarEvent::create([
            'class_id' => $class->id, 'created_by' => $staff->id, 'title' => 'Clone verification event',
            'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(),
        ]));
        Carbon::setTestNow('2026-09-27 13:00:00');
        $result = app(DelayedCloneApplier::class)->applyEligible(1000);
        $this->assertSame(0, $result['failed']);
        $this->assertTrue(DB::connection('sqlite_clone')->table('calendar_events')->where('id', $event->id)->exists());
        $this->assertSame(WorkspaceNotification::count(), DB::connection('sqlite_clone')->table('workspace_notifications')->count());
        $notification = WorkspaceNotification::where('type', 'CalendarEvent')->firstOrFail();
        app(DelayedCloneWrite::class)->transaction(fn () => $notification->update(['read_at' => now()]));
        Carbon::setTestNow('2026-09-28 02:00:00');
        app(DelayedCloneApplier::class)->applyEligible(1000);
        $this->assertNotNull(DB::connection('sqlite_clone')->table('workspace_notifications')->find($notification->id)->read_at);
    }

    public function test_create_update_and_delete_reach_sqlite_only_after_twelve_hours(): void
    {
        Carbon::setTestNow('2026-08-28 08:00:00');
        $writes = app(DelayedCloneWrite::class);
        $applier = app(DelayedCloneApplier::class);

        $user = $writes->transaction(fn () => User::factory()->create([
            'email' => 'delayed-clone@example.test',
            'first_name' => 'Original',
        ]));

        $event = CloneOutbox::query()->firstOrFail();
        $this->assertSame(CloneOutbox::OPERATION_CREATE, $event->operation);
        $this->assertSame('2026-08-28 20:00:00', $event->available_at->format('Y-m-d H:i:s'));
        $this->assertSame(0, $applier->applyEligible()['applied']);
        $this->assertFalse($this->cloneHasUser($user->id));

        Carbon::setTestNow('2026-08-28 20:00:00');
        $this->assertSame(1, $applier->applyEligible()['applied']);
        $this->assertTrue($this->cloneHasUser($user->id));

        $writes->transaction(fn () => $user->update(['first_name' => 'Updated']));
        $this->assertSame('Original', DB::connection('sqlite_clone')->table('users')->find($user->id)->first_name);

        Carbon::setTestNow('2026-08-29 08:00:00');
        $this->assertSame(1, $applier->applyEligible()['applied']);
        $this->assertSame('Updated', DB::connection('sqlite_clone')->table('users')->find($user->id)->first_name);

        $writes->transaction(fn () => $user->delete());
        $this->assertTrue($this->cloneHasUser($user->id));

        Carbon::setTestNow('2026-08-29 20:00:00');
        $this->assertSame(1, $applier->applyEligible()['applied']);
        $this->assertFalse($this->cloneHasUser($user->id));
    }

    public function test_domain_write_and_outbox_event_roll_back_together(): void
    {
        try {
            app(DelayedCloneWrite::class)->transaction(function (): void {
                User::factory()->create(['email' => 'rollback@example.test']);

                throw new RuntimeException('Force the transaction to roll back.');
            });

            $this->fail('The transaction should have thrown an exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Force the transaction to roll back.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.test']);
        $this->assertDatabaseCount('clone_outbox', 0);
    }

    public function test_new_species_metadata_waits_twelve_hours_before_reaching_the_clone(): void
    {
        Carbon::setTestNow('2026-09-13 08:00:00');
        $species = Species::query()->create([
            'slug' => 'test-fish',
            'common_name' => 'Test Fish',
            'scientific_name' => 'Piscis exemplum',
            'taxonomic_group' => 'Actinopterygii',
            'activity_mode' => 'anatomy_only',
        ]);

        $this->assertFalse(DB::connection('sqlite_clone')->table('species')->where('id', $species->id)->exists());
        $this->assertSame(0, app(DelayedCloneApplier::class)->applyEligible()['applied']);

        Carbon::setTestNow('2026-09-13 20:00:00');
        $this->assertSame(1, app(DelayedCloneApplier::class)->applyEligible()['applied']);
        $this->assertTrue(DB::connection('sqlite_clone')->table('species')->where('id', $species->id)->exists());
    }

    public function test_complete_species_class_assignment_and_result_graph_is_cloned_after_the_delay(): void
    {
        Carbon::setTestNow('2026-09-13 08:00:00');
        $records = app(DelayedCloneWrite::class)->transaction(function (): array {
            $instructor = User::factory()->create(['role' => User::ROLE_INSTRUCTOR, 'email' => 'clone-instructor@example.test']);
            $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'email' => 'clone-student@example.test']);
            $species = Species::query()->create([
                'slug' => 'clone-fish',
                'common_name' => 'Clone Fish',
                'scientific_name' => 'Piscis duplicatus',
                'taxonomic_group' => 'Actinopterygii',
                'activity_mode' => 'anatomy_only',
            ]);
            $package = SpeciesPackage::query()->create([
                'species_id' => $species->id,
                'version' => '1.0.0',
                'schema_version' => '1.0',
                'status' => SpeciesPackage::STATUS_DRAFT,
                'viewer_profile' => 'layered-2d',
                'minimum_app_version' => '1.0.0',
                'manifest' => ['schemaVersion' => '1.0', 'packageVersion' => '1.0.0'],
                'archive_path' => 'species-packages/clone-fish/1.0.0',
                'size_bytes' => 128,
                'imported_by' => $instructor->id,
            ]);
            $asset = SpeciesPackageAsset::query()->create([
                'species_package_id' => $package->id,
                'asset_key' => 'natural',
                'relative_path' => 'assets/natural.webp',
                'mime_type' => 'image/webp',
                'size_bytes' => 128,
                'sha256' => str_repeat('a', 64),
            ]);
            $class = LearningClass::query()->create(['code' => 'CLONE-101', 'name' => 'Clone Biology', 'status' => 'active']);
            $classInstructor = ClassInstructor::query()->create(['class_id' => $class->id, 'user_id' => $instructor->id]);
            $enrollment = ClassEnrollment::query()->create(['class_id' => $class->id, 'user_id' => $student->id, 'status' => 'active']);
            $assignment = SpeciesAssignment::query()->create([
                'class_id' => $class->id,
                'species_id' => $species->id,
                'species_package_id' => $package->id,
                'assigned_by' => $instructor->id,
                'assigned_at' => now(),
                'active' => true,
            ]);
            $event = SpeciesPackageEvent::query()->create([
                'species_package_id' => $package->id,
                'user_id' => $instructor->id,
                'event' => 'draft_imported',
            ]);
            $review = SpeciesPackageReview::query()->create([
                'species_package_id' => $package->id,
                'reviewer_id' => $instructor->id,
                'reviewer_role' => 'Faculty Reviewer',
                'review_round' => 1,
                'content_fingerprint' => str_repeat('c', 64),
                'decision' => 'changes_requested',
                'notes' => 'Clone verification species review record.',
                'curriculum_alignment_confirmed' => false,
                'safety_review_confirmed' => false,
            ]);
            $result = LessonResult::query()->create([
                'user_id' => $student->id,
                'species_package_id' => $package->id,
                'lesson_key' => 'clone-fish-anatomy',
                'attempt_uuid' => (string) Str::uuid(),
                'package_version' => '1.0.0',
                'score' => 1,
                'total' => 1,
                'source' => 'online',
                'completed_at' => now(),
            ]);

            return [
                'users' => [$instructor->id, $student->id],
                'species' => [$species->id],
                'species_packages' => [$package->id],
                'species_package_assets' => [$asset->id],
                'classes' => [$class->id],
                'class_instructors' => [$classInstructor->id],
                'class_enrollments' => [$enrollment->id],
                'species_assignments' => [$assignment->id],
                'species_package_events' => [$event->id],
                'species_package_reviews' => [$review->id],
                'lesson_results' => [$result->id],
            ];
        });

        foreach ($records as $table => $ids) {
            foreach ($ids as $id) {
                $this->assertFalse(DB::connection('sqlite_clone')->table($table)->where('id', $id)->exists());
            }
        }
        $this->assertSame(0, app(DelayedCloneApplier::class)->applyEligible()['applied']);

        Carbon::setTestNow('2026-09-13 20:00:00');
        $report = app(DelayedCloneApplier::class)->applyEligible();
        $this->assertSame(0, $report['failed']);
        foreach ($records as $table => $ids) {
            foreach ($ids as $id) {
                $this->assertTrue(DB::connection('sqlite_clone')->table($table)->where('id', $id)->exists());
            }
        }
    }

    public function test_complete_learning_module_graph_is_cloned_after_the_delay(): void
    {
        Carbon::setTestNow('2026-09-19 08:00:00');
        $records = app(DelayedCloneWrite::class)->transaction(function (): array {
            $staff = User::factory()->create(['role' => User::ROLE_INSTRUCTOR, 'email' => 'module-staff@example.test']);
            $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'email' => 'module-student@example.test']);
            $class = LearningClass::query()->create(['code' => 'MODULE-CLONE', 'name' => 'Module Clone Section', 'status' => 'active']);
            $module = LearningModule::query()->create([
                'slug' => 'clone-safety-module',
                'title' => 'Clone Safety Module',
                'category' => 'pre-laboratory',
                'description' => 'Clone verification record.',
            ]);
            $version = LearningModuleVersion::query()->create([
                'learning_module_id' => $module->id,
                'version' => '0.1.0',
                'status' => LearningModuleVersion::STATUS_DRAFT,
                'manifest' => ['requiredStepIds' => ['orientation'], 'tools' => []],
                'is_current' => false,
                'imported_by' => $staff->id,
            ]);
            $asset = LearningModuleAsset::query()->create([
                'learning_module_version_id' => $version->id,
                'asset_key' => 'orientation-image',
                'relative_path' => 'learning-modules/clone/asset.webp',
                'mime_type' => 'image/webp',
                'size_bytes' => 128,
                'sha256' => str_repeat('b', 64),
                'metadata' => ['source' => 'test', 'licence' => 'test', 'caption' => 'Clone verification image'],
            ]);
            $assignment = ClassModuleAssignment::query()->create([
                'class_id' => $class->id,
                'learning_module_id' => $module->id,
                'learning_module_version_id' => $version->id,
                'assigned_by' => $staff->id,
                'required' => true,
                'active' => true,
                'assigned_at' => now(),
            ]);
            $progress = LearningModuleProgress::query()->create([
                'user_id' => $student->id,
                'class_id' => $class->id,
                'learning_module_version_id' => $version->id,
                'completed_step_ids' => ['orientation'],
                'source' => 'offline',
                'completed_at' => now(),
                'synced_at' => now(),
            ]);
            $attempt = LearningModuleAttempt::query()->create([
                'user_id' => $student->id,
                'class_id' => $class->id,
                'learning_module_version_id' => $version->id,
                'attempt_uuid' => (string) Str::uuid(),
                'score' => 0,
                'total' => 1,
                'source' => 'offline',
                'completed_at' => now(),
                'submitted_at' => now(),
                'synced_at' => now(),
            ]);
            $review = LearningModuleReview::query()->create([
                'learning_module_version_id' => $version->id,
                'reviewer_id' => $staff->id,
                'reviewer_role' => 'Faculty Reviewer',
                'decision' => 'changes_requested',
                'notes' => 'Clone verification review record.',
            ]);
            $override = LearningModuleOverride::query()->create([
                'class_id' => $class->id,
                'user_id' => $student->id,
                'learning_module_id' => $module->id,
                'reason' => 'Clone verification exception record.',
                'granted_by' => $staff->id,
                'granted_at' => now(),
            ]);
            $prompt = MediaPromptRecord::query()->create([
                'learning_module_version_id' => $version->id,
                'actor_id' => $staff->id,
                'tool_key' => 'probe',
                'media_type' => 'illustration',
                'prompt_text' => 'Clone verification media prompt.',
                'options' => ['view' => 'side'],
                'attached_at' => now(),
            ]);

            return [
                'learning_modules' => [$module->id],
                'learning_module_versions' => [$version->id],
                'learning_module_assets' => [$asset->id],
                'class_module_assignments' => [$assignment->id],
                'learning_module_progress' => [$progress->id],
                'learning_module_attempts' => [$attempt->id],
                'learning_module_reviews' => [$review->id],
                'learning_module_overrides' => [$override->id],
                'media_prompt_records' => [$prompt->id],
            ];
        });

        foreach ($records as $table => $ids) {
            foreach ($ids as $id) {
                $this->assertFalse(DB::connection('sqlite_clone')->table($table)->where('id', $id)->exists());
            }
        }
        $this->assertSame(0, app(DelayedCloneApplier::class)->applyEligible()['applied']);

        Carbon::setTestNow('2026-09-19 20:00:00');
        $report = app(DelayedCloneApplier::class)->applyEligible();
        $this->assertSame(0, $report['failed']);
        foreach ($records as $table => $ids) {
            foreach ($ids as $id) {
                $this->assertTrue(DB::connection('sqlite_clone')->table($table)->where('id', $id)->exists());
            }
        }
    }

    private function cloneHasUser(int $userId): bool
    {
        return DB::connection('sqlite_clone')->table('users')->where('id', $userId)->exists();
    }
}
