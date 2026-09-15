<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\ClassInstructor;
use App\Models\CloneOutbox;
use App\Models\LearningClass;
use App\Models\LessonResult;
use App\Models\Species;
use App\Models\SpeciesAssignment;
use App\Models\SpeciesPackage;
use App\Models\SpeciesPackageAsset;
use App\Models\SpeciesPackageEvent;
use App\Models\User;
use App\Services\DelayedCloneApplier;
use App\Services\DelayedCloneWrite;
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

    private function cloneHasUser(int $userId): bool
    {
        return DB::connection('sqlite_clone')->table('users')->where('id', $userId)->exists();
    }
}
