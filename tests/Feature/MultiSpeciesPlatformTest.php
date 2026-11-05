<?php

namespace Tests\Feature;

use App\Models\LearningClass;
use App\Models\LessonResult;
use App\Models\SpeciesAssignment;
use App\Models\SpeciesPackage;
use App\Models\User;
use App\Services\SpeciesPackageLifecycle;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RabbitfishGuidedClipsSeeder;
use Database\Seeders\SpeciesCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class MultiSpeciesPlatformTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $cleanupPaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise retained multi-species platform behavior with the temporary UI focus disabled.
        config(['species-packages.visible_slugs' => []]);
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupPaths as $path) {
            File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
        }

        parent::tearDown();
    }

    public function test_bundled_catalog_has_published_tilapia_and_rabbitfish_ready_for_review(): void
    {
        $tilapia = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'tilapia'))->where('is_current', true)->firstOrFail();
        $rabbitfish = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'rabbitfish'))->firstOrFail();

        $this->assertSame(SpeciesPackage::STATUS_PUBLISHED, $tilapia->status);
        $this->assertTrue($tilapia->is_current);
        $this->assertSame('1.1.0', $tilapia->version);
        $this->assertCount(7, $tilapia->assets);
        $this->assertNotNull($tilapia->assets->firstWhere('asset_key', 'video'));
        $this->assertSame(SpeciesPackage::STATUS_REVIEW, $rabbitfish->status);
        $this->assertSame('1.1.0', $rabbitfish->version);
        $this->assertCount(7, $rabbitfish->assets);
        $this->assertSame('video/mp4', $rabbitfish->assets->firstWhere('asset_key', 'video')?->mime_type);
        $this->assertSame('Siganus guttatus', $rabbitfish->species->scientific_name);
        $this->assertDatabaseHas('class_enrollments', ['user_id' => User::query()->where('role', 'student')->value('id')]);
    }

    public function test_catalog_and_manifest_follow_student_and_instructor_boundaries(): void
    {
        $student = User::query()->where('role', 'student')->firstOrFail();
        $instructor = User::query()->where('role', 'instructor')->firstOrFail();
        $rabbitfish = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'rabbitfish'))->firstOrFail();

        $this->actingAs($student)->getJson('/api/species')
            ->assertOk()
            ->assertJsonCount(1, 'species')
            ->assertJsonPath('species.0.slug', 'tilapia')
            ->assertJsonPath('species.0.hasVideo', true);
        $this->actingAs($student)
            ->getJson("/api/species/rabbitfish/packages/{$rabbitfish->id}/manifest")
            ->assertForbidden();

        $this->actingAs($instructor)->getJson('/api/species')
            ->assertOk()
            ->assertJsonCount(3, 'species');
        $this->actingAs($instructor)
            ->getJson("/api/species/rabbitfish/packages/{$rabbitfish->id}/manifest")
            ->assertOk()
            ->assertJsonPath('package.contentData.quiz.formative', true)
            ->assertJsonPath('package.assetRecords.video.mimeType', 'video/mp4')
            ->assertJsonPath('package.assetProvenance.video.aiGenerated', false)
            ->assertJsonPath('package.assetProvenance.video.reviewStatus', 'awaiting_faculty_review')
            ->assertJsonCount(7, 'package.assetRecords');
    }

    public function test_guided_preparation_clips_are_available_to_staff_in_the_unpublished_version(): void
    {
        $this->seed(RabbitfishGuidedClipsSeeder::class);
        $this->seed(RabbitfishGuidedClipsSeeder::class);
        $package = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'rabbitfish'))
            ->where('version', RabbitfishGuidedClipsSeeder::VERSION)->sole();
        $instructor = User::query()->where('role', 'instructor')->firstOrFail();
        $student = User::query()->where('role', 'student')->firstOrFail();
        $manifestUrl = "/api/species/rabbitfish/packages/{$package->id}/manifest";

        $this->assertSame(SpeciesPackage::STATUS_REVIEW, $package->status);
        $this->assertFalse($package->is_current);
        $this->assertSame(1, $package->events()->where('event', 'prepared_for_faculty_review')->count());

        $response = $this->actingAs($instructor)->getJson($manifestUrl)->assertOk();
        foreach (['clip-01', 'clip-02', 'clip-03', 'clip-04'] as $index => $key) {
            $response->assertJsonPath("package.contentData.lesson.steps.{$index}.videoAsset", $key)
                ->assertJsonPath("package.assetRecords.{$key}.mimeType", 'video/mp4');
            $assetUrl = "/api/species-packages/{$package->id}/assets/".$response->json("package.assetRecords.{$key}.id");
            $this->actingAs($instructor)->get($assetUrl)->assertOk()->assertHeader('Content-Type', 'video/mp4');
            $this->actingAs($student)->get($assetUrl)->assertForbidden();
        }
        $response->assertJsonPath('package.contentData.lesson.steps.4.title', 'Meet the Goldspotted Rabbitfish');
        $this->actingAs($student)->getJson($manifestUrl)->assertForbidden();
    }

    public function test_versioned_attempt_is_scored_by_server_and_client_uuid_is_idempotent(): void
    {
        $student = User::query()->where('role', 'student')->firstOrFail();
        $package = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'tilapia'))->where('is_current', true)->firstOrFail();
        $attempt = [
            'packageId' => $package->id,
            'packageVersion' => $package->version,
            'clientAttemptUuid' => (string) Str::uuid(),
            'source' => 'offline',
            'completedAt' => now()->subMinute()->toIso8601String(),
            'answers' => ['q1' => 'Swim bladder', 'q2' => 'Heart', 'q3' => 'Lateral line'],
        ];

        $this->actingAs($student)->postJson('/api/lessons/tilapia-guided-dissection/attempts', $attempt)
            ->assertCreated()
            ->assertJsonPath('result.score', 2)
            ->assertJsonPath('result.total', 3)
            ->assertJsonPath('duplicate', false);
        $this->actingAs($student)->postJson('/api/lessons/tilapia-guided-dissection/attempts', $attempt)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertSame(1, LessonResult::query()->where('attempt_uuid', $attempt['clientAttemptUuid'])->count());
    }

    public function test_admin_publication_and_instructor_assignment_are_role_scoped(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $instructor = User::query()->where('role', 'instructor')->firstOrFail();
        $student = User::query()->where('role', 'student')->firstOrFail();
        $class = LearningClass::query()->where('code', 'FISHBIO-101')->firstOrFail();
        $rabbitfish = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'rabbitfish'))->firstOrFail();

        $this->actingAs($student)->postJson("/api/admin/species-packages/{$rabbitfish->id}/publish")->assertForbidden();
        $this->actingAs($admin)->postJson("/api/admin/species-packages/{$rabbitfish->id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('review');
        $this->actingAs($instructor)->postJson("/api/staff/species-packages/{$rabbitfish->id}/review", [
            'decision' => 'approved',
            'notes' => 'Uploader must not be able to approve the same package.',
            'evidenceReference' => 'Faculty review evidence',
            'curriculumAlignmentConfirmed' => true,
            'safetyReviewConfirmed' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('reviewer');
        $this->actingAs($admin)->postJson("/api/staff/species-packages/{$rabbitfish->id}/review", [
            'decision' => 'approved',
            'notes' => 'The anatomy sequence and specimen safety directions are ready for publication.',
            'evidenceReference' => 'Faculty review evidence',
            'curriculumAlignmentConfirmed' => true,
            'safetyReviewConfirmed' => true,
        ])->assertOk()->assertJsonPath('package.approvalCurrent', true);
        $updatedManifest = $rabbitfish->fresh()->manifest;
        $updatedManifest['reviewVerification'] = 'content changed after approval';
        $rabbitfish->fresh()->update(['manifest' => $updatedManifest]);
        $this->actingAs($admin)->postJson("/api/admin/species-packages/{$rabbitfish->id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('review');
        $this->actingAs($admin)->postJson("/api/staff/species-packages/{$rabbitfish->id}/review", [
            'decision' => 'approved',
            'notes' => 'The current content fingerprint was checked after the package update.',
            'evidenceReference' => 'Faculty review evidence revision 2',
            'curriculumAlignmentConfirmed' => true,
            'safetyReviewConfirmed' => true,
        ])->assertOk()->assertJsonPath('package.approvalCurrent', true);
        $this->actingAs($admin)->postJson("/api/admin/species-packages/{$rabbitfish->id}/publish")
            ->assertOk()
            ->assertJsonPath('package.status', 'published');
        $this->actingAs($instructor)->postJson("/api/instructor/classes/{$class->id}/assignments", [
            'speciesPackageId' => $rabbitfish->id,
        ])->assertOk();

        $this->actingAs($student)->getJson('/api/species')->assertJsonCount(2, 'species');
    }

    public function test_importer_accepts_a_valid_new_version_and_rejects_forbidden_files(): void
    {
        $instructor = User::query()->where('role', 'instructor')->firstOrFail();
        $version = '1.1.'.random_int(10, 99);
        $archive = $this->makeRabbitfishArchive($version);

        $this->actingAs($instructor)->post('/api/instructor/species-packages', [
            'package' => new UploadedFile($archive, 'rabbitfish.aqpack', 'application/zip', null, true),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('package.version', $version)
            ->assertJsonPath('package.status', 'draft');

        $stored = storage_path("app/private/species-packages/rabbitfish/{$version}");
        $this->cleanupPaths[] = $stored;

        $unsafePath = storage_path('framework/testing/unsafe-'.Str::uuid().'.aqpack');
        File::ensureDirectoryExists(dirname($unsafePath));
        $zip = new ZipArchive;
        $zip->open($unsafePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('../payload.php', '<?php echo "unsafe";');
        $zip->close();
        $this->cleanupPaths[] = $unsafePath;

        $this->actingAs($instructor)->post('/api/instructor/species-packages', [
            'package' => new UploadedFile($unsafePath, 'unsafe.aqpack', 'application/zip', null, true),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('archive');
    }

    public function test_importer_accepts_a_valid_package_with_a_swimming_video(): void
    {
        $instructor = User::query()->where('role', 'instructor')->firstOrFail();
        $version = '1.2.'.random_int(10, 99);
        $archive = $this->makeTilapiaVideoArchive($version);

        $this->actingAs($instructor)->post('/api/instructor/species-packages', [
            'package' => new UploadedFile($archive, 'tilapia-video.aqpack', 'application/zip', null, true),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('package.version', $version)
            ->assertJsonPath('package.hasVideo', true);

        $package = SpeciesPackage::query()->where('version', $version)->firstOrFail();
        $this->assertSame('video/mp4', $package->assets()->where('asset_key', 'video')->value('mime_type'));
        $this->cleanupPaths[] = storage_path("app/private/species-packages/tilapia/{$version}");
    }

    public function test_publishing_a_new_version_upgrades_only_unstarted_assignments(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $student = User::query()->where('role', 'student')->firstOrFail();
        $old = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'tilapia'))->where('is_current', true)->firstOrFail();
        $manifest = $old->manifest;
        $manifest['packageVersion'] = '1.2.0';
        $new = SpeciesPackage::query()->create([
            'species_id' => $old->species_id,
            'version' => '1.2.0',
            'schema_version' => '1.0',
            'status' => SpeciesPackage::STATUS_REVIEW,
            'viewer_profile' => 'layered-2d',
            'minimum_app_version' => '1.0.0',
            'manifest' => $manifest,
            'archive_path' => $old->archive_path,
            'size_bytes' => $old->size_bytes,
            'imported_by' => $admin->id,
        ]);
        LessonResult::query()->create([
            'user_id' => $student->id,
            'species_package_id' => $old->id,
            'lesson_key' => 'tilapia-guided-dissection',
            'attempt_uuid' => (string) Str::uuid(),
            'package_version' => $old->version,
            'score' => 1,
            'total' => 3,
            'source' => 'online',
            'completed_at' => now(),
        ]);
        $unstartedClass = LearningClass::query()->create(['code' => 'FISHBIO-102', 'name' => 'Fish Biology 102', 'status' => 'active']);
        SpeciesAssignment::query()->create([
            'class_id' => $unstartedClass->id,
            'species_id' => $old->species_id,
            'species_package_id' => $old->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $instructor = User::query()->where('role', 'instructor')->firstOrFail();
        app(SpeciesPackageLifecycle::class)->review(
            $new,
            $instructor,
            'approved',
            'The new package version matches the reviewed anatomy and safety requirements.',
            'Faculty review evidence',
            true,
            true,
        );
        $result = app(SpeciesPackageLifecycle::class)->publish($new->fresh(), $admin);

        $this->assertSame(1, $result['upgradedAssignments']);
        $this->assertSame(1, $result['pinnedAssignments']);
        $this->assertDatabaseHas('species_assignments', ['class_id' => $unstartedClass->id, 'species_package_id' => $new->id]);
        $this->assertDatabaseHas('species_assignments', ['class_id' => LearningClass::query()->where('code', 'FISHBIO-101')->value('id'), 'species_package_id' => $old->id]);

        $this->actingAs($admin)->postJson("/api/admin/species-packages/{$old->id}/activate")
            ->assertOk()
            ->assertJsonPath('package.isCurrent', true);
        $this->assertFalse($new->fresh()->is_current);
    }

    public function test_changes_and_restored_packages_require_a_new_independent_review(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $instructor = User::query()->where('role', 'instructor')->firstOrFail();
        $rabbitfish = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'rabbitfish'))->firstOrFail();

        $this->actingAs($admin)->postJson("/api/staff/species-packages/{$rabbitfish->id}/review", [
            'decision' => 'changes_requested',
            'notes' => 'Clarify the specimen-specific spine handling instructions before publication.',
            'evidenceReference' => null,
            'curriculumAlignmentConfirmed' => false,
            'safetyReviewConfirmed' => false,
        ])->assertOk()->assertJsonPath('package.status', 'draft');

        $this->actingAs($instructor)->postJson("/api/instructor/species-packages/{$rabbitfish->id}/submit")->assertOk();
        $this->actingAs($admin)->postJson("/api/staff/species-packages/{$rabbitfish->id}/review", [
            'decision' => 'approved',
            'notes' => 'The revised package meets the anatomy sequence and specimen safety requirements.',
            'evidenceReference' => 'Faculty review evidence',
            'curriculumAlignmentConfirmed' => true,
            'safetyReviewConfirmed' => true,
        ])->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/species-packages/{$rabbitfish->id}/publish")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/species-packages/{$rabbitfish->id}/archive")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/species-packages/{$rabbitfish->id}/restore")
            ->assertOk()
            ->assertJsonPath('package.status', 'review')
            ->assertJsonPath('package.approvalCurrent', false);
        $this->actingAs($admin)->postJson("/api/admin/species-packages/{$rabbitfish->id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('review');

        $this->assertDatabaseCount('species_package_reviews', 2);
        $this->assertDatabaseHas('species_package_events', [
            'species_package_id' => $rabbitfish->id,
            'event' => 'restored_for_review',
        ]);
    }

    public function test_bundled_seeding_preserves_published_lifecycle_and_review_records(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $rabbitfish = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'rabbitfish'))->firstOrFail();
        $lifecycle = app(SpeciesPackageLifecycle::class);

        $lifecycle->review(
            $rabbitfish,
            $admin,
            'approved',
            'The current anatomy, movement reference, and specimen safety directions were independently reviewed.',
            'Faculty review evidence',
            true,
            true,
        );
        $published = $lifecycle->publish($rabbitfish->fresh(), $admin)['package']->fresh();
        $publishedAt = $published->published_at?->toIso8601String();
        $reviewedBy = $published->reviewed_by;

        $this->seed(SpeciesCatalogSeeder::class);

        $reseeded = $published->fresh(['assets', 'reviews']);
        $this->assertSame(SpeciesPackage::STATUS_PUBLISHED, $reseeded->status);
        $this->assertTrue($reseeded->is_current);
        $this->assertSame($publishedAt, $reseeded->published_at?->toIso8601String());
        $this->assertSame($reviewedBy, $reseeded->reviewed_by);
        $this->assertCount(1, $reseeded->reviews);
        $this->assertCount(7, $reseeded->assets);
        $this->assertTrue($lifecycle->approvalIsCurrent($reseeded));
    }

    public function test_bundled_seeding_rejects_checksum_changes_to_an_existing_version(): void
    {
        $rabbitfish = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'rabbitfish'))->firstOrFail();
        $rabbitfish->update(['checksum' => str_repeat('0', 64)]);

        try {
            $this->seed(SpeciesCatalogSeeder::class);
            $this->fail('The seeder accepted changed content under an existing bundled version.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('differs from the installed immutable version', $exception->getMessage());
        }

        $this->assertSame(SpeciesPackage::STATUS_REVIEW, $rabbitfish->fresh()->status);
        $this->assertDatabaseCount('species_package_reviews', 0);
    }

    public function test_bundled_seeding_rejects_declared_source_file_drift(): void
    {
        $sourceRoot = storage_path('framework/testing/species-source-'.Str::uuid());
        $sourcePath = $sourceRoot.DIRECTORY_SEPARATOR.'content'.DIRECTORY_SEPARATOR.'quiz.json';
        File::ensureDirectoryExists(dirname($sourcePath));
        File::put($sourcePath, '{"formative":true}');
        $expectedHash = hash_file('sha256', $sourcePath);
        File::put($sourcePath, '{"formative":false}');
        $this->cleanupPaths[] = $sourceRoot;

        $seeder = new class extends SpeciesCatalogSeeder
        {
            /** @param array<string, mixed> $manifest */
            public function validateSource(string $sourceRoot, array $manifest): void
            {
                $this->validateBundledFiles($sourceRoot, $manifest, 'test-species', '1.0.0');
            }
        };

        try {
            $seeder->validateSource($sourceRoot, [
                'files' => ['content/quiz.json' => $expectedHash],
            ]);
            $this->fail('The seeder accepted a source file that no longer matched its declared checksum.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('source checksum mismatch', $exception->getMessage());
            $this->assertStringContainsString('content/quiz.json', $exception->getMessage());
        }
    }

    public function test_bundled_seeding_rejects_drift_in_versioned_storage(): void
    {
        $rabbitfish = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'rabbitfish'))->firstOrFail();
        $asset = $rabbitfish->assets()->where('asset_key', 'content.quiz')->firstOrFail();
        $disk = Storage::disk(config('species-packages.disk'));
        $storedPath = trim($rabbitfish->archive_path, '/').'/'.$asset->relative_path;
        $original = $disk->get($storedPath);

        try {
            $disk->put($storedPath, $original."\n");
            $this->seed(SpeciesCatalogSeeder::class);
            $this->fail('The seeder accepted changed bytes in immutable versioned storage.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('stored file differs from its immutable source', $exception->getMessage());
            $this->assertStringContainsString($asset->relative_path, $exception->getMessage());
        } finally {
            $disk->put($storedPath, $original);
        }

        $this->assertSame(SpeciesPackage::STATUS_REVIEW, $rabbitfish->fresh()->status);
    }

    public function test_approval_fingerprint_detects_changed_storage_bytes(): void
    {
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $rabbitfish = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'rabbitfish'))->firstOrFail();
        $asset = $rabbitfish->assets()->where('asset_key', 'content.quiz')->firstOrFail();
        $lifecycle = app(SpeciesPackageLifecycle::class);
        $lifecycle->review(
            $rabbitfish,
            $admin,
            'approved',
            'The stored package bytes and safety guidance were independently reviewed.',
            'Faculty review evidence',
            true,
            true,
        );
        $this->assertTrue($lifecycle->approvalIsCurrent($rabbitfish->fresh()));

        $disk = Storage::disk(config('species-packages.disk'));
        $storedPath = trim($rabbitfish->archive_path, '/').'/'.$asset->relative_path;
        $original = $disk->get($storedPath);
        try {
            $disk->put($storedPath, $original."\n");
            $this->assertFalse($lifecycle->approvalIsCurrent($rabbitfish->fresh()));
            $this->actingAs($admin)->postJson("/api/admin/species-packages/{$rabbitfish->id}/publish")
                ->assertUnprocessable()
                ->assertJsonValidationErrors('review');
        } finally {
            $disk->put($storedPath, $original);
        }

        $this->assertSame(SpeciesPackage::STATUS_REVIEW, $rabbitfish->fresh()->status);
    }

    public function test_staff_catalog_orders_newer_review_versions_first(): void
    {
        $instructor = User::query()->where('role', 'instructor')->firstOrFail();
        $newer = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'rabbitfish'))->firstOrFail();
        $olderManifest = $newer->manifest;
        $olderManifest['packageVersion'] = '1.0.0';
        SpeciesPackage::query()->create([
            'species_id' => $newer->species_id,
            'version' => '1.0.0',
            'schema_version' => '1.0',
            'status' => SpeciesPackage::STATUS_REVIEW,
            'viewer_profile' => 'layered-2d',
            'minimum_app_version' => '1.0.0',
            'manifest' => $olderManifest,
            'archive_path' => $newer->archive_path,
            'checksum' => str_repeat('1', 64),
            'size_bytes' => $newer->size_bytes,
            'imported_by' => $instructor->id,
            'review_round' => 1,
        ]);

        $response = $this->actingAs($instructor)->getJson('/api/species')->assertOk();
        $rabbitfishVersions = collect($response->json('species'))
            ->where('slug', 'rabbitfish')
            ->pluck('version')
            ->values()
            ->all();

        $this->assertSame(['1.1.0', '1.0.0'], $rabbitfishVersions);
    }

    public function test_published_package_content_is_immutable_and_export_is_available_to_instructors(): void
    {
        $instructor = User::query()->where('role', 'instructor')->firstOrFail();
        $package = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'tilapia'))->where('is_current', true)->firstOrFail();

        $this->actingAs($instructor)->get("/api/species-packages/{$package->id}/download")
            ->assertOk()
            ->assertDownload('tilapia-1.1.0.aqpack');

        $this->expectException(\LogicException::class);
        $package->update(['manifest' => [...$package->manifest, 'lessonKey' => 'changed']]);
    }

    public function test_published_package_asset_records_reject_updates_and_deletes(): void
    {
        $package = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'tilapia'))->where('is_current', true)->firstOrFail();
        $asset = $package->assets()->where('asset_key', 'natural')->firstOrFail();
        $originalMime = $asset->mime_type;

        try {
            $asset->update(['mime_type' => 'application/octet-stream']);
            $this->fail('A published package asset record was updated.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('published species package', $exception->getMessage());
        }
        $this->assertSame($originalMime, $asset->fresh()->mime_type);

        try {
            $asset->fresh()->delete();
            $this->fail('A published package asset record was deleted.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('published species package', $exception->getMessage());
        }
        $this->assertDatabaseHas('species_package_assets', ['id' => $asset->id]);
    }

    private function makeRabbitfishArchive(string $version): string
    {
        $source = resource_path('species-packs/rabbitfish/1.1.0');
        $archive = storage_path('framework/testing/rabbitfish-'.$version.'.aqpack');
        File::ensureDirectoryExists(dirname($archive));
        $manifest = json_decode((string) File::get($source.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $manifest['packageVersion'] = $version;

        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        foreach (array_keys($manifest['files']) as $relativePath) {
            $zip->addFile($source.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath), $relativePath);
        }
        $zip->close();
        $this->cleanupPaths[] = $archive;

        return $archive;
    }

    private function makeTilapiaVideoArchive(string $version): string
    {
        $source = resource_path('species-packs/tilapia/1.1.0');
        $archive = storage_path('framework/testing/tilapia-'.$version.'.aqpack');
        File::ensureDirectoryExists(dirname($archive));
        $manifest = json_decode((string) File::get($source.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $manifest['packageVersion'] = $version;
        $manifest['viewer'] = ['profile' => 'layered-2d'];

        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        foreach (array_keys($manifest['files']) as $relativePath) {
            $zip->addFile($source.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath), $relativePath);
        }
        $zip->close();
        $this->cleanupPaths[] = $archive;

        return $archive;
    }
}
