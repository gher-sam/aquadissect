<?php

namespace Tests\Feature;

use App\Models\LearningClass;
use App\Models\LessonResult;
use App\Models\User;
use App\Services\CloneRefresher;
use App\Services\DelayedCloneApplier;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountRosterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_existing_class_is_attached_to_one_reusable_course_without_changing_enrollment_or_results(): void
    {
        $class = LearningClass::query()->where('code', 'FISHBIO-101')->firstOrFail();
        $student = User::query()->where('role', User::ROLE_STUDENT)->firstOrFail();

        $this->assertSame('MB-304L', $class->course->code);
        $this->assertSame('Legacy', $class->academic_year);
        $this->assertDatabaseHas('class_enrollments', ['class_id' => $class->id, 'user_id' => $student->id]);

        $this->actingAs($student)->getJson('/api/classes')->assertOk()
            ->assertJsonPath('classes.0.courseCode', 'MB-304L')
            ->assertJsonMissingPath('classes.0.students.0.email');
    }

    public function test_only_administrator_can_create_and_reuse_a_term_section(): void
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();
        $instructor = User::query()->where('role', User::ROLE_INSTRUCTOR)->firstOrFail();
        $payload = ['academicYear' => '2026-2027', 'semester' => '1', 'sectionCode' => 'A'];

        $this->actingAs($instructor)->postJson('/api/instructor/classes', ['code' => 'EXTRA', 'name' => 'Extra'])->assertNotFound();
        $this->actingAs($instructor)->postJson('/api/admin/offerings', $payload)->assertForbidden();
        $this->actingAs($admin)->postJson('/api/admin/offerings', $payload)->assertCreated();
        $this->actingAs($admin)->postJson('/api/admin/offerings', $payload)->assertUnprocessable();

        $this->assertDatabaseCount('courses', 1);
        $this->assertDatabaseHas('classes', ['academic_year' => '2026-2027', 'semester' => '1', 'section_code' => 'A']);
    }

    public function test_admin_provisions_student_once_and_student_activates_with_single_use_code(): void
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();
        $class = LearningClass::query()->where('code', 'FISHBIO-101')->firstOrFail();
        $payload = ['classId' => $class->id, 'firstName' => 'Lina', 'lastName' => 'Cruz', 'email' => 'lina@example.test', 'identityNumber' => 'ST-2026-001'];

        $this->postJson('/api/admin/students', $payload)->assertUnauthorized();
        $response = $this->actingAs($admin)->postJson('/api/admin/students', $payload)->assertCreated();
        $code = $response->json('activationCodes.0.code');
        $this->assertSame(48, strlen($code));
        $this->assertDatabaseHas('users', ['email' => 'lina@example.test', 'status' => 'pending']);
        $this->assertDatabaseHas('class_enrollments', ['class_id' => $class->id, 'user_id' => User::query()->where('email', 'lina@example.test')->value('id')]);

        $this->actingAs($admin)->postJson('/api/admin/students', $payload)->assertCreated()->assertJsonCount(0, 'activationCodes');
        $this->assertSame(1, User::query()->where('email', 'lina@example.test')->count());

        auth()->logout();
        $activation = ['email' => 'lina@example.test', 'code' => $code, 'password' => 'newPassword123', 'password_confirmation' => 'newPassword123'];
        $this->postJson('/api/activate', $activation)->assertOk();
        $this->postJson('/api/activate', $activation)->assertUnprocessable();
        $this->postJson('/api/login', ['email' => 'lina@example.test', 'password' => 'newPassword123'])->assertOk();
    }

    public function test_csv_preview_flags_conflicts_and_repeat_import_is_idempotent(): void
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();
        $class = LearningClass::query()->where('code', 'FISHBIO-101')->firstOrFail();
        $csv = "first_name,last_name,email,identity_number\nLina,Cruz,lina@example.test,ST-2026-001\n";
        $file = fn () => UploadedFile::fake()->createWithContent('roster.csv', $csv);

        $this->actingAs($admin)->post('/api/admin/roster/preview', ['classId' => $class->id, 'roster' => $file()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('rows.0.status', 'new');
        $this->actingAs($admin)->post('/api/admin/roster/import', ['classId' => $class->id, 'roster' => $file()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('rows.0.status', 'new');
        $this->actingAs($admin)->post('/api/admin/roster/import', ['classId' => $class->id, 'roster' => $file()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('rows.0.status', 'already_enrolled');
        $this->assertSame(1, User::query()->where('identity_number', 'ST-2026-001')->count());

        $conflict = "first_name,last_name,email,identity_number\nLina,Cruz,lina@example.test,DIFFERENT-ID\n";
        $this->actingAs($admin)->post('/api/admin/roster/preview', ['classId' => $class->id, 'roster' => UploadedFile::fake()->createWithContent('conflict.csv', $conflict)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('rows.0.status', 'conflict');
        $this->actingAs($admin)->post('/api/admin/roster/import', ['classId' => $class->id, 'roster' => UploadedFile::fake()->createWithContent('conflict.csv', $conflict)], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    public function test_instructor_assignment_controls_roster_visibility_and_archiving_preserves_history(): void
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();
        $instructor = User::factory()->create(['role' => User::ROLE_INSTRUCTOR, 'status' => User::STATUS_ACTIVE]);
        $class = LearningClass::query()->where('code', 'FISHBIO-101')->firstOrFail();
        $student = User::query()->where('role', User::ROLE_STUDENT)->firstOrFail();

        $this->actingAs($instructor)->getJson('/api/classes')->assertOk()->assertJsonCount(0, 'classes');
        $this->actingAs($admin)->postJson("/api/admin/offerings/{$class->id}/instructors", ['instructorId' => $instructor->id])->assertOk();
        $this->actingAs($instructor)->getJson('/api/classes')->assertOk()->assertJsonPath('classes.0.students.0.id', $student->id);
        $this->actingAs($admin)->patchJson("/api/admin/offerings/{$class->id}/status", ['status' => 'archived'])->assertOk();
        $this->assertDatabaseHas('class_enrollments', ['class_id' => $class->id, 'user_id' => $student->id]);
        $this->actingAs($student)->getJson('/api/species')->assertOk()->assertJsonCount(0, 'species');
    }

    public function test_new_attempt_records_its_section_and_legacy_results_remain_unattributed(): void
    {
        $student = User::query()->where('role', User::ROLE_STUDENT)->firstOrFail();
        $class = LearningClass::query()->where('code', 'FISHBIO-101')->firstOrFail();
        $package = $class->assignments()->with('package')->firstOrFail()->package;
        $this->actingAs($student)->postJson('/api/lesson-results', ['score' => 1, 'total' => 3])->assertCreated();
        $this->assertNull(LessonResult::query()->latest('id')->value('class_id'));

        $attempt = [
            'classId' => $class->id,
            'packageId' => $package->id,
            'packageVersion' => $package->version,
            'clientAttemptUuid' => (string) Str::uuid(),
            'source' => 'online',
            'completedAt' => now()->toIso8601String(),
            'answers' => ['q1' => 'Swim bladder', 'q2' => 'Heart', 'q3' => 'Lateral line'],
        ];
        $this->actingAs($student)->postJson('/api/lessons/tilapia-guided-dissection/attempts', $attempt)->assertCreated();
        $this->assertDatabaseHas('lesson_results', ['attempt_uuid' => $attempt['clientAttemptUuid'], 'class_id' => $class->id]);
    }

    public function test_new_roster_rows_reach_sqlite_only_after_twelve_hours(): void
    {
        config(['delayed-clone.enabled' => true, 'delayed-clone.delay_hours' => 12]);
        config()->set('database.connections.sqlite_clone.database', ':memory:');
        DB::purge('sqlite_clone');
        Artisan::call('migrate:fresh', ['--database' => 'sqlite_clone', '--force' => true]);
        app(CloneRefresher::class)->refresh();
        $admin = User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();
        $class = LearningClass::query()->where('code', 'FISHBIO-101')->firstOrFail();
        Carbon::setTestNow('2026-09-17 08:00:00');
        $this->actingAs($admin)->postJson('/api/admin/students', [
            'classId' => $class->id, 'firstName' => 'Lina', 'lastName' => 'Cruz',
            'email' => 'clone-roster@example.test', 'identityNumber' => 'ST-CLONE-001',
        ])->assertCreated();
        $user = User::query()->where('email', 'clone-roster@example.test')->firstOrFail();
        $this->assertFalse(DB::connection('sqlite_clone')->table('users')->where('id', $user->id)->exists());
        Carbon::setTestNow('2026-09-17 20:00:00');
        $report = app(DelayedCloneApplier::class)->applyEligible();
        $this->assertSame(0, $report['failed']);
        $this->assertTrue(DB::connection('sqlite_clone')->table('users')->where('id', $user->id)->exists());
        $this->assertTrue(DB::connection('sqlite_clone')->table('class_enrollments')->where('user_id', $user->id)->exists());
        Carbon::setTestNow();
    }
}
