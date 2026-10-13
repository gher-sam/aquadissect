<?php

namespace Tests\Feature;

use App\Models\{User, TutorialProgress, LessonResult, SpeciesPackage};
use App\Services\TutorialCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentOrientationTest extends TestCase
{
    use RefreshDatabase;

    public function test_orientation_is_available_without_lessons_and_progress_is_private(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = User::where('role', 'student')->firstOrFail();
        $guide = app(TutorialCatalog::class)->guides('student')[0];
        $this->assertSame('student-orientation', $guide['id']);
        $this->assertCount(5, $guide['steps']);
        $before = LessonResult::count();
        $statuses = SpeciesPackage::pluck('status', 'id')->all();
        $this->actingAs($student)->getJson('/api/species')->assertJsonCount(0, 'species');
        $this->putJson('/api/tutorials/student-orientation/progress', ['version'=>1,'step'=>'finish','viewed'=>true])->assertOk();
        $this->assertNotNull(TutorialProgress::where('user_id', $student->id)->where('guide_key','student-orientation')->firstOrFail()->viewed_at);
        $this->assertSame($before, LessonResult::count());
        $this->assertSame($statuses, SpeciesPackage::pluck('status', 'id')->all());
        $other = User::factory()->create(['role'=>'student','status'=>'active','first_name'=>'Learner','last_name'=>'Two']);
        $this->actingAs($other)->getJson('/api/tutorials')->assertOk()->assertJsonCount(0,'progress');
        $this->get('/help?guide=student-orientation')->assertOk();
    }
}
