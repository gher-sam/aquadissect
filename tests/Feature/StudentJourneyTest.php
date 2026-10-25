<?php

namespace Tests\Feature;

use App\Models\{User, LearningClass, LearningModuleVersion, SpeciesPackage, LessonResult};
use App\Services\{LearningModuleLifecycle, SpeciesPackageLifecycle, SpeciesPackageContent};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_orientation_safety_rabbitfish_and_saved_results_form_one_account_scoped_journey(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('role', 'admin')->firstOrFail();
        $teacher = User::where('role', 'instructor')->firstOrFail();
        $reviewer = User::factory()->create(['first_name'=>'Faculty', 'last_name'=>'Demo', 'role'=>'instructor', 'status'=>'active']);
        $class = LearningClass::where('code', 'FISHBIO-101')->firstOrFail();
        $created = $this->actingAs($admin)->postJson('/api/admin/students', [
            'classId'=>$class->id, 'firstName'=>'Journey', 'lastName'=>'Demo',
            'email'=>'journey@example.test', 'identityNumber'=>'JOURNEY-DEMO',
        ])->assertCreated();
        $code = $created->json('activationCodes.0.code');
        $student = User::where('email', 'journey@example.test')->firstOrFail();
        auth()->logout();
        $activation = ['email'=>$student->email,'code'=>$code,'password'=>'JourneyTest123!','password_confirmation'=>'JourneyTest123!'];
        $this->postJson('/api/activate', $activation)->assertOk();
        $this->postJson('/api/activate', $activation)->assertUnprocessable();
        $this->postJson('/api/login',['email'=>$student->email,'password'=>'JourneyTest123!'])->assertOk();
        $student->refresh();
        $this->putJson('/api/tutorials/student-orientation/progress',['version'=>1,'step'=>'finish','viewed'=>true])->assertOk();
        $this->assertSame(0, LessonResult::where('user_id',$student->id)->count());
        $module = LearningModuleVersion::where('version','0.2.0')->firstOrFail();
        $package = SpeciesPackage::where('version','1.1.0')->whereHas('species',fn($q)=>$q->where('slug','rabbitfish'))->firstOrFail();
        $this->getJson("/api/learning-modules/{$module->id}/manifest?classId={$class->id}")->assertForbidden();
        $this->getJson("/api/species/rabbitfish/packages/{$package->id}/manifest?classId={$class->id}")->assertForbidden();

        // Synthetic approvals exist only in this isolated test database.
        $modules = app(LearningModuleLifecycle::class);
        $module = $modules->submit($module,$admin);
        $module = $modules->review($module,$reviewer,'approved','Synthetic QA-only workflow fixture.','QA fixture, not actual faculty evidence',true);
        $modules->publish($module,$admin,[$class->id]);
        $species = app(SpeciesPackageLifecycle::class);
        $package = $species->review($package,$reviewer,'approved','Synthetic QA-only workflow fixture.','QA fixture, not actual faculty evidence',true,true);
        $species->publish($package,$admin);
        $this->actingAs($teacher)->postJson("/api/instructor/classes/{$class->id}/assignments",['speciesPackageId'=>$package->id])->assertOk();
        $url = "/api/species/rabbitfish/packages/{$package->id}/manifest?classId={$class->id}";
        $this->actingAs($student)->getJson($url)->assertUnprocessable();
        $progressUrl = "/api/learning-module-versions/{$module->id}/progress";
        $required = $module->manifest['requiredStepIds'];
        $activity = [
            'classId'=>$class->id,'completedStepIds'=>['tool-selection'],
            'clientAttemptUuid'=>(string)Str::uuid(),
            'answers'=>collect($module->manifest['activity']['questions'])->mapWithKeys(fn($q)=>[$q['id']=>'deliberately incorrect'])->all(),
            'source'=>'online','completedAt'=>now()->toIso8601String(),
        ];
        $this->postJson($progressUrl,$activity)->assertCreated()->assertJsonPath('progress.completed',false)->assertJsonPath('progress.activitySubmitted',true)->assertJsonPath('attempt.score',0);
        $this->postJson($progressUrl,$activity)->assertOk()->assertJsonPath('duplicate',true);
        $otherClass = $class->replicate();
        $otherClass->fill(['code'=>'JOURNEY-OTHER','name'=>'Other demonstration section','academic_year'=>'2027-2028','semester'=>'1','section_code'=>'QA'])->save();
        \App\Models\ClassModuleAssignment::create(['class_id'=>$otherClass->id,'learning_module_id'=>$module->learning_module_id,'learning_module_version_id'=>$module->id,'required'=>true,'active'=>true,'assigned_by'=>$teacher->id,'assigned_at'=>now()]);
        \App\Models\ClassEnrollment::create(['class_id'=>$otherClass->id,'user_id'=>$student->id,'status'=>'active']);
        $this->postJson($progressUrl,[...$activity,'classId'=>$otherClass->id])->assertConflict();
        $this->assertDatabaseMissing('learning_module_progress',['user_id'=>$student->id,'class_id'=>$otherClass->id]);
        $this->getJson($url)->assertUnprocessable();
        $this->getJson('/api/learning-modules')->assertJsonPath('learningModules.0.progress.activitySubmitted',true);
        $this->postJson($progressUrl,[
            'classId'=>$class->id,'completedStepIds'=>$required,'source'=>'online','completedAt'=>now()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('progress.completed',true);
        $manifest = $this->getJson($url)->assertOk()->json('package');
        $this->assertCount(10,$manifest['contentData']['lesson']['steps']);
        $quiz = app(SpeciesPackageContent::class)->manifest($package->fresh())['contentData']['quiz'];
        $attempt = [
            'classId'=>$class->id,'packageId'=>$package->id,'packageVersion'=>$package->version,
            'clientAttemptUuid'=>(string)Str::uuid(),'source'=>'online','completedAt'=>now()->toIso8601String(),
            'answers'=>collect($quiz['questions'])->mapWithKeys(fn($q)=>[$q['id']=>$q['answer']])->all(),
        ];
        $attemptUrl = '/api/lessons/'.$manifest['lessonKey'].'/attempts';
        $this->postJson($attemptUrl,$attempt)->assertCreated()->assertJsonPath('result.score',count($quiz['questions']));
        $this->postJson($attemptUrl,$attempt)->assertOk()->assertJsonPath('duplicate',true);
        $this->postJson($attemptUrl,[...$attempt,'classId'=>$otherClass->id])->assertConflict();
        $this->assertSame(1,LessonResult::where('user_id',$student->id)->count());
        $this->postJson('/api/logout')->assertOk();
        $this->postJson('/api/login',['email'=>$student->email,'password'=>'JourneyTest123!'])->assertOk()->assertJsonPath('lessonResult.class_id',$class->id);
        $this->getJson('/api/tutorials')->assertOk()->assertJsonPath('progress.0.guide_key','student-orientation');
        $this->getJson('/api/learning-modules')->assertOk()->assertJsonPath('learningModules.0.progress.completed',true);
        $other = User::factory()->create(['first_name'=>'Other','last_name'=>'Demo','role'=>'student','status'=>'active']);
        $this->actingAs($other)->getJson('/api/tutorials')->assertJsonCount(0,'progress');
        $this->getJson($url)->assertForbidden();
    }
}
