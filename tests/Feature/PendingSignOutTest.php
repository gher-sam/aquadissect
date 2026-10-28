<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TutorialCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PendingSignOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconnection_finishes_pending_logout_before_returning_private_content_and_preserves_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = User::where('role', 'student')->firstOrFail();
        $password = $student->password;
        $enrollments = DB::table('class_enrollments')->get()->toJson();
        $results = DB::table('lesson_results')->get()->toJson();
        $this->actingAs($student)->withUnencryptedCookie('aquadissect_logout_pending', '1')
            ->get('/dashboard')->assertRedirect('/login')->assertCookieExpired('aquadissect_logout_pending');
        $this->assertGuest();
        $this->assertSame($password, $student->fresh()->password);
        $this->assertSame($enrollments, DB::table('class_enrollments')->get()->toJson());
        $this->assertSame($results, DB::table('lesson_results')->get()->toJson());
    }

    public function test_pending_logout_blocks_api_work_and_finishes_even_for_a_password_change_account(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = User::where('role', 'student')->firstOrFail();
        $student->update(['must_change_password' => true]);
        $this->actingAs($student)->withCredentials()->withUnencryptedCookie('aquadissect_logout_pending', '1')
            ->postJson('/api/lesson-results', [])->assertUnauthorized()->assertJsonPath('signedOut', true);
        $this->assertGuest();
        $this->assertDatabaseCount('lesson_results', 0);
        $this->actingAs($student)->withCredentials()->withUnencryptedCookie('aquadissect_logout_pending', '1')
            ->postJson('/api/logout')->assertOk()->assertJsonPath('signedOut', true);
        $this->assertGuest();
    }

    public function test_current_account_guidance_matches_enabled_initial_password_policy(): void
    {
        config(['student-setup.initial_password_enabled' => true]);
        $catalog = app(TutorialCatalog::class);
        $admin = json_encode($catalog->guides('admin'));
        $this->assertStringContainsString('student ID as both username and temporary password', $admin);
        $this->assertStringContainsString('Password history', $admin);
        $this->assertStringNotContainsString('Otherwise, they use the one-time code', $admin);
        $this->assertStringContainsString('No email or setup code is required', json_encode($catalog->guides('student')));
        config(['student-setup.initial_password_enabled' => false, 'student-setup.password_enabled' => true]);
        $this->assertStringContainsString('private setup code', json_encode($catalog->guides('admin')));
    }
}
