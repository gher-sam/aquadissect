<?php

namespace Tests\Feature;

use App\Models\ClassModuleAssignment;
use App\Models\LearningClass;
use App\Models\LearningModuleOverride;
use App\Models\LearningModuleVersion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotebookReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_checklist_receives_its_own_confirmed_gate_without_changing_academic_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = User::where('role', 'student')->firstOrFail();
        $other = User::factory()->create(['first_name' => 'QA', 'last_name' => 'Learner', 'role' => 'student', 'status' => 'active']);
        $admin = User::where('role', 'admin')->firstOrFail();
        $class = LearningClass::firstOrFail();
        $version = LearningModuleVersion::where('version', '0.2.0')->firstOrFail();
        $version->update(['status' => 'published']);
        ClassModuleAssignment::create([
            'class_id' => $class->id, 'learning_module_id' => $version->learning_module_id,
            'learning_module_version_id' => $version->id, 'assigned_by' => $admin->id,
            'required' => true, 'active' => true, 'assigned_at' => now(),
        ]);
        LearningModuleOverride::create([
            'class_id' => $class->id, 'learning_module_id' => $version->learning_module_id,
            'user_id' => $other->id, 'granted_by' => $admin->id, 'granted_at' => now(), 'reason' => 'Synthetic account isolation check.',
        ]);
        $before = [];
        foreach (['users', 'class_enrollments', 'lesson_results', 'tutorial_progress'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $response = $this->actingAs($student)->getJson('/api/classes')->assertOk();
        $section = collect($response->json('classes'))->firstWhere('id', $class->id);
        $this->assertSame(['satisfied' => false, 'required' => 1, 'completed' => 0, 'overridden' => 0], $section['prerequisiteStatus']);
        $this->assertSame([], $section['students']);
        foreach ($before as $table => $records) {
            $this->assertSame($records, DB::table($table)->orderBy('id')->get()->toJson());
        }
        LearningModuleOverride::create([
            'class_id' => $class->id, 'learning_module_id' => $version->learning_module_id,
            'user_id' => $student->id, 'granted_by' => $admin->id, 'granted_at' => now(), 'reason' => 'Synthetic documented exception check.',
        ]);
        $response = $this->getJson('/api/classes')->assertOk();
        $gate = collect($response->json('classes'))->firstWhere('id', $class->id)['prerequisiteStatus'];
        $this->assertTrue($gate['satisfied']);
        $this->assertSame(0, $gate['completed']);
        $this->assertSame(1, $gate['overridden']);
    }
}
