<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\ClassInstructor;
use App\Models\LearningClass;
use App\Models\SpeciesAssignment;
use App\Models\SpeciesPackage;
use App\Models\User;
use App\Services\AnatomyRegionSchema;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class AnatomyRegionsTest extends TestCase
{
    use RefreshDatabase;

    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $path) {
            $resolved = realpath($path);
            if ($resolved === false) {
                continue;
            }
            $this->assertTrue(str_starts_with($resolved, realpath(storage_path()).DIRECTORY_SEPARATOR));
            if (is_dir($path)) {
                File::deleteDirectory($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    private function archive(?callable $edit = null): UploadedFile
    {
        $slug = 'qa-outline-'.Str::lower(Str::random(8));
        $image = imagecreatetruecolor(120, 80);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        $anatomy = ['structures' => [['id' => 'test-fin', 'name' => 'Synthetic region', 'layer' => 'external', 'summary' => 'Engineering fixture only', 'detail' => 'Not biological instruction', 'system' => 'Engineering fixture', 'color' => '#155eef']],
            'regionMaps' => ['external' => ['layer' => 'external', 'imageSha256' => hash('sha256', $png), 'width' => 120, 'height' => 80, 'basis' => 'illustration',
                'sources' => [['title' => 'Engineering fixture reference', 'url' => 'https://example.org/reference']],
                'parts' => ['test-fin' => ['label' => 'Synthetic region', 'polygons' => [[[10, 10], [60, 10], [60, 60], [10, 60]]]]], 'unmapped' => []]]];
        $manifest = ['schemaVersion' => '1.0', 'packageVersion' => '1.0.0', 'minimumApplicationVersion' => '1.0.0', 'species' => ['slug' => $slug, 'commonName' => 'QA outline specimen', 'scientificName' => 'Synthetic engineering fixture', 'taxonomicGroup' => 'Engineering fixture'],
            'lessonKey' => $slug, 'activityMode' => 'anatomy_only', 'catalogVisibility' => 'listed', 'viewer' => ['profile' => 'layered-2d'],
            'safetyNotices' => ['Engineering fixture only; not instructional content.'], 'references' => [['title' => 'Engineering fixture', 'url' => 'https://example.org/reference']], 'licenses' => [['name' => 'Synthetic engineering fixture']],
            'assets' => ['natural' => 'assets/natural.png', 'external' => 'assets/external.png', 'internal' => 'assets/internal.png'],
            'content' => ['anatomy' => 'content/anatomy.json', 'lesson' => 'content/lesson.json', 'quiz' => 'content/quiz.json']];
        if ($edit) {
            $edit($anatomy, $manifest);
        }
        $files = ['assets/natural.png' => $png, 'assets/external.png' => $png, 'assets/internal.png' => $png,
            'content/anatomy.json' => json_encode($anatomy), 'content/lesson.json' => json_encode(['title' => 'Synthetic region lesson', 'description' => 'Engineering only', 'duration' => 'Engineering fixture', 'steps' => [['id' => 'look', 'kind' => 'targets', 'title' => 'Engineering example', 'targets' => ['test-fin']]]]),
            'content/quiz.json' => json_encode(['formative' => true, 'questions' => []])];
        $manifest['files'] = array_map(fn ($bytes) => hash('sha256', $bytes), $files);
        $path = storage_path('framework/testing/region-'.Str::uuid().'.aqpack');
        File::ensureDirectoryExists(dirname($path));
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $file => $bytes) {
            $zip->addFromString($file, $bytes);
        }
        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->close();
        $this->cleanup[] = $path;
        $this->cleanup[] = storage_path('app/private/species-packages/'.$slug);

        return new UploadedFile($path, 'synthetic.aqpack', 'application/zip', null, true);
    }

    public function test_new_species_imports_as_draft_and_reaches_only_its_assigned_student_after_independent_review(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['species-packages.visible_slugs' => ['rabbitfish']]);
        $instructor = User::where('role', 'instructor')->firstOrFail();
        $admin = User::where('role', 'admin')->firstOrFail();
        $reviewer = User::factory()->create(['role' => 'program_head', 'first_name' => 'Faculty', 'last_name' => 'Reviewer', 'status' => 'active']);
        $student = User::where('role', 'student')->firstOrFail();
        $response = $this->actingAs($instructor)->post('/api/instructor/species-packages', ['package' => $this->archive()], ['Accept' => 'application/json'])->assertCreated();
        $package = SpeciesPackage::findOrFail($response->json('package.id'));
        $this->assertSame('draft', $package->status);
        $this->getJson('/api/species')->assertJsonFragment(['id' => $package->id]);
        $section = LearningClass::create(['code' => 'QA-REGION', 'name' => 'Synthetic outline section', 'status' => 'active']);
        ClassInstructor::create(['class_id' => $section->id, 'user_id' => $instructor->id]);
        ClassEnrollment::create(['class_id' => $section->id, 'user_id' => $student->id, 'status' => 'active']);
        SpeciesAssignment::create(['class_id' => $section->id, 'species_id' => $package->species_id, 'species_package_id' => $package->id, 'assigned_by' => $instructor->id, 'assigned_at' => now(), 'active' => true]);
        $url = '/api/species/'.$package->species->slug.'/packages/'.$package->id.'/manifest?classId='.$section->id;
        $this->actingAs($student)->getJson($url)->assertForbidden();
        $this->actingAs($admin)->postJson('/api/admin/species-packages/'.$package->id.'/publish')->assertUnprocessable();
        $this->actingAs($instructor)->postJson('/api/instructor/species-packages/'.$package->id.'/submit')->assertOk();
        $this->actingAs($reviewer)->postJson('/api/staff/species-packages/'.$package->id.'/review', ['decision' => 'approved', 'notes' => 'Synthetic engineering review, not biological validation.', 'evidenceReference' => 'Synthetic engineering fixture only', 'curriculumAlignmentConfirmed' => true, 'safetyReviewConfirmed' => true])->assertOk();
        $this->actingAs($admin)->postJson('/api/admin/species-packages/'.$package->id.'/publish')->assertOk();
        $this->actingAs($student)->getJson($url)->assertOk()->assertJsonPath('package.contentData.anatomy.regionMaps.external.parts.test-fin.label', 'Synthetic region');
        $this->getJson('/api/species')->assertJsonFragment(['id' => $package->id])->assertJsonMissing(['slug' => 'tilapia']);
        $this->getJson('/api/classes')->assertJsonFragment(['packageId' => $package->id]);
        $other = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $this->actingAs($other)->getJson($url)->assertForbidden();
    }

    public function test_import_rejects_unsafe_or_unbound_annotations(): void
    {
        $actor = User::factory()->create(['role' => 'instructor', 'status' => 'active']);
        $before = SpeciesPackage::count();
        foreach (['hash', 'label', 'crossing', 'outside', 'reference', 'missing'] as $problem) {
            $file = $this->archive(function (&$anatomy, &$manifest) use ($problem): void {
                $map = &$anatomy['regionMaps']['external'];
                if ($problem === 'hash') {
                    $map['imageSha256'] = str_repeat('0', 64);
                }
                if ($problem === 'label') {
                    $map['parts']['test-fin']['label'] = 'Different structure';
                }
                if ($problem === 'crossing') {
                    $map['parts']['test-fin']['polygons'] = [[[10, 10], [60, 60], [10, 60], [60, 10]]];
                }
                if ($problem === 'outside') {
                    $map['parts']['test-fin']['polygons'][0][0][0] = 101;
                }
                if ($problem === 'reference') {
                    $map['sources'][0]['url'] = 'javascript:alert(1)';
                }
                if ($problem === 'missing') {
                    unset($anatomy['regionMaps']);
                }
            });
            $this->actingAs($actor)->post('/api/instructor/species-packages', ['package' => $file], ['Accept' => 'application/json'])->assertUnprocessable();
        }
        $this->assertSame($before, SpeciesPackage::count());
    }

    public function test_geometry_checks_match_browser_rules(): void
    {
        $this->assertTrue(AnatomyRegionSchema::validPolygon([[10, 10], [30, 10], [30, 30], [10, 30]]));
        foreach ([[[0, 0], [30, 30], [0, 30], [30, 0]], [[0, 0], [10, 10], [20, 20]], [[1, 1], [1, 1], [10, 20]], [[0, 0], [101, 0], [10, 10]], [[0, 0], [INF, 0], [2, 2]]] as $points) {
            $this->assertFalse(AnatomyRegionSchema::validPolygon($points));
        }
    }
}
