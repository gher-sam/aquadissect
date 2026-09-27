<?php
namespace Tests\Feature;
use App\Models\{User, LearningClass, CalendarEvent, WorkspaceNotification, LessonResult};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceToolsTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(DatabaseSeeder::class); }
    private function person(string $role): User { return User::where('role', $role)->firstOrFail(); }
    private function eventData(): array { return ['class_id'=>LearningClass::firstOrFail()->id, 'title'=>'Laboratory orientation', 'location'=>'Teaching laboratory', 'starts_at'=>'2026-09-28T09:00:00+08:00', 'ends_at'=>'2026-09-28T10:00:00+08:00']; }
    public function test_readable_pages_are_scoped_and_only_render_their_own_panel(): void {
        $this->get('/calendar')->assertRedirect('/login');
        foreach (['student','instructor','admin'] as $role) {
            $this->actingAs($this->person($role));
            foreach (['dashboard','lessons','safety-tools','class-sections','offline-access','calendar','notifications','reports','help','account-roles'] as $path) $this->get('/'.$path)->assertOk();
            $this->get('/')->assertRedirect('/dashboard');
            $this->get('/people-sections')->assertStatus($role==='admin'?200:403);
            $this->get('/activities')->assertStatus($role==='instructor'?200:403);
            $this->get('/species-content')->assertStatus($role==='student'?403:200);
        }
        $this->get('/calendar')->assertSee('data-portal-panel="calendar"',false)->assertDontSee('id="loginForm"',false);
        $this->get('/offline')->assertOk()->assertDontSee($this->person('admin')->email);
    }
    public function test_calendar_authorization_utc_update_and_cancellation(): void {
        $payload=$this->eventData();
        $this->actingAs($this->person('student'))->postJson('/api/calendar/events',$payload)->assertForbidden();
        $outside=User::factory()->create(['role'=>'instructor']);
        $this->actingAs($outside)->postJson('/api/calendar/events',$payload)->assertForbidden();
        $id=$this->actingAs($this->person('instructor'))->postJson('/api/calendar/events',$payload)->assertCreated()->json('id');
        $this->assertSame('2026-09-28 01:00:00',CalendarEvent::findOrFail($id)->starts_at->format('Y-m-d H:i:s'));
        $count=WorkspaceNotification::where('type','CalendarEvent')->count();
        $this->assertGreaterThanOrEqual(2,$count);
        $this->patchJson('/api/calendar/events/'.$id,$payload)->assertOk();
        $this->assertSame($count,WorkspaceNotification::where('type','CalendarEvent')->count());
        $this->patchJson('/api/calendar/events/'.$id,array_merge($payload,['title'=>'Updated orientation']))->assertOk();
        $this->assertSame($count*2,WorkspaceNotification::where('type','CalendarEvent')->count());
        $this->actingAs($outside)->postJson('/api/calendar/events/'.$id.'/cancel')->assertForbidden();
        $this->actingAs($this->person('admin'))->postJson('/api/calendar/events/'.$id.'/cancel')->assertOk();
        $this->patchJson('/api/calendar/events/'.$id,$payload)->assertUnprocessable();
        $query='/api/calendar?from=2026-09-01T00:00:00Z&to=2026-10-01T00:00:00Z';
        $this->actingAs($this->person('student'))->getJson($query)->assertOk()->assertJsonPath('events.0.cancelled',true);
        $this->actingAs($outside)->getJson($query)->assertOk()->assertJsonCount(0,'events');
    }
    public function test_notifications_are_private_and_read_actions_are_idempotent(): void {
        $this->actingAs($this->person('admin'))->postJson('/api/calendar/events',$this->eventData())->assertCreated();
        $student=$this->person('student');
        $mine=WorkspaceNotification::where('user_id',$student->id)->where('type','CalendarEvent')->firstOrFail();
        $other=WorkspaceNotification::where('user_id',$this->person('instructor')->id)->where('type','CalendarEvent')->firstOrFail();
        $this->actingAs($student)->postJson('/api/notifications/'.$other->id.'/read')->assertNotFound();
        $this->postJson('/api/notifications/'.$mine->id.'/read')->assertOk();
        $this->postJson('/api/notifications/read-all')->assertOk();
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('unread',0);
        $this->assertNull($other->fresh()->read_at);
    }
    public function test_assignment_and_approval_notifications_reach_the_intended_roles(): void {
        $assignment = \App\Models\SpeciesAssignment::firstOrFail();
        $assignment->update(['due_at'=>'2026-10-03 00:00:00']);
        $recipients=WorkspaceNotification::where('type','SpeciesAssignment')->pluck('user_id')->sort()->values()->all();
        $expected=collect([$this->person('student')->id,$this->person('instructor')->id])->sort()->values()->all();
        $this->assertSame($expected,$recipients);
        $pending=User::factory()->create(['role'=>'instructor','status'=>'pending']);
        $this->assertDatabaseHas('workspace_notifications',['user_id'=>$this->person('admin')->id,'type'=>'User','summary'=>'An instructor access request needs review.']);
        $pending->update(['status'=>'active']);
        $this->assertDatabaseHas('workspace_notifications',['user_id'=>$pending->id,'summary'=>'Your instructor account has been approved.']);
        $student=$this->person('student');
        \App\Models\ClassEnrollment::where('user_id',$student->id)->update(['status'=>'withdrawn']);
        $this->actingAs($student)->getJson('/api/notifications')->assertOk()->assertJsonPath('unread',0);
    }

    public function test_reports_keep_other_learners_private_and_export_safe_values(): void {
        $student=$this->person('student'); $class=LearningClass::firstOrFail();
        $student->update(['first_name'=>'=FORMULA','last_name'=>'Learner']);
        LessonResult::create(['user_id'=>$student->id,'class_id'=>$class->id,'lesson_key'=>'recorded-test','score'=>3,'total'=>5,'completed_at'=>'2026-09-27 15:30:00']);
        $other=User::factory()->create(['role'=>'student']);
        LessonResult::create(['user_id'=>$other->id,'class_id'=>null,'lesson_key'=>'private-record','score'=>1,'total'=>5,'completed_at'=>now()]);
        $response=$this->actingAs($student)->getJson('/api/reports?activity=lessons&from=2026-09-27&to=2026-09-27')->assertOk();
        $this->assertTrue(collect($response->json('rows'))->contains('activity','recorded-test'));
        $response->assertDontSee('private-record');
        $csv=$this->get('/api/reports?format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=FORMULA",$csv);
        $this->assertStringNotContainsString('private-record',$csv);
        $this->getJson('/api/reports?to=2026-09-27')->assertOk();
        $outsider=User::factory()->create(['role'=>'instructor']);
        $this->actingAs($outsider)->getJson('/api/reports?classId='.$class->id)->assertForbidden();
        $this->getJson('/api/reports')->assertOk()->assertJsonCount(0,'rows');
    }
}
