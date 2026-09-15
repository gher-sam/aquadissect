<?php

namespace Tests\Feature;

use App\Models\LearningClass;
use App\Models\LessonResult;
use App\Models\SpeciesAssignment;
use App\Models\SpeciesPackage;
use App\Models\User;
use App\Services\SpeciesPackageLifecycle;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupPaths as $path) {
            File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
        }

        parent::tearDown();
    }

    public function test_bundled_catalog_has_published_tilapia_and_instructor_owned_rabbitfish_draft(): void
    {
        $tilapia = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'tilapia'))->where('is_current', true)->firstOrFail();
        $rabbitfish = SpeciesPackage::query()->whereHas('species', fn ($query) => $query->where('slug', 'rabbitfish'))->firstOrFail();

        $this->assertSame(SpeciesPackage::STATUS_PUBLISHED, $tilapia->status);
        $this->assertTrue($tilapia->is_current);
        $this->assertSame('1.1.0', $tilapia->version);
        $this->assertCount(7, $tilapia->assets);
        $this->assertNotNull($tilapia->assets->firstWhere('asset_key', 'video'));
        $this->assertSame(SpeciesPackage::STATUS_DRAFT, $rabbitfish->status);
        $this->assertCount(6, $rabbitfish->assets);
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
            ->assertJsonCount(6, 'package.assetRecords');
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

        $result = app(SpeciesPackageLifecycle::class)->publish($new, $admin);

        $this->assertSame(1, $result['upgradedAssignments']);
        $this->assertSame(1, $result['pinnedAssignments']);
        $this->assertDatabaseHas('species_assignments', ['class_id' => $unstartedClass->id, 'species_package_id' => $new->id]);
        $this->assertDatabaseHas('species_assignments', ['class_id' => LearningClass::query()->where('code', 'FISHBIO-101')->value('id'), 'species_package_id' => $old->id]);

        $this->actingAs($admin)->postJson("/api/admin/species-packages/{$old->id}/activate")
            ->assertOk()
            ->assertJsonPath('package.isCurrent', true);
        $this->assertFalse($new->fresh()->is_current);
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

    private function makeRabbitfishArchive(string $version): string
    {
        $source = resource_path('species-packs/rabbitfish/1.0.0');
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
