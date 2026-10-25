<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AquaDissectTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_page_loads_with_the_tilapia_viewer(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')
            ->assertOk()
            ->assertSee('AquaDissect')
            ->assertSee('AQUADISSECT_BASE_URL')
            ->assertSee('portalSidebar')
            ->assertSee('dashboardMenuButton')
            ->assertSee('installAppButton')
            ->assertSee('href="#portal-main"', false)
            ->assertSee('aria-label="Primary navigation"', false)
            ->assertSee('id="portal-main"', false)
            ->assertSee('id="portalConfirmDialog"', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('manifest.webmanifest')
            ->assertSee('apple-touch-icon.png')
            ->assertSee('viewport-fit=cover')
            ->assertSee('csrf-token');
    }

    public function test_pwa_manifest_icons_and_service_worker_are_complete(): void
    {
        $manifest = json_decode((string) File::get(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('./', $manifest['id']);
        $this->assertSame('./', $manifest['start_url']);
        $this->assertSame('./', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('#0e315b', $manifest['theme_color']);
        $this->assertCount(3, $manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            $path = public_path($icon['src']);
            $this->assertFileExists($path);
            $dimensions = getimagesize($path);
            $this->assertNotFalse($dimensions);
            $this->assertSame($icon['sizes'], "{$dimensions[0]}x{$dimensions[1]}");
        }
        $this->assertFileExists(public_path('pwa-icons/apple-touch-icon.png'));

        $worker = (string) File::get(public_path('aquadissect-sw.js'));
        $this->assertStringContainsString('aquadissect-shell-v17', $worker);
        $this->assertStringContainsString('self.registration.scope', $worker);
        $this->assertStringContainsString('manifest.webmanifest', $worker);
        $this->assertStringContainsString('caches.delete', $worker);
        $this->assertStringContainsString('Content-Range', $worker);
    }

    public function test_active_user_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create([
            'email' => 'learner@example.test',
            'password' => 'secret123',
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertOk()->assertJsonPath('user.role', 'student');

        $this->assertAuthenticatedAs($user);
        $this->postJson('/api/logout')->assertOk();
        $this->assertGuest();
    }

    public function test_public_registration_only_accepts_instructor_requests(): void
    {
        $student = [
            'firstName' => 'Lina',
            'lastName' => 'Cruz',
            'email' => 'lina@example.test',
            'identityNumber' => 'ST-2026-001',
            'role' => 'student',
            'classCode' => 'FISHBIO-101',
            'password' => 'secret123',
            'agreement' => true,
        ];

        $this->postJson('/api/register', $student)->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseMissing('users', ['email' => $student['email']]);

        $this->postJson('/api/register', [
            ...$student,
            'email' => 'teacher@example.test',
            'identityNumber' => 'FAC-2026-001',
            'role' => 'instructor',
            'classCode' => null,
        ])->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'teacher@example.test', 'status' => 'pending']);
    }

    public function test_authenticated_student_can_store_a_lesson_result(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->postJson('/api/lesson-results', ['score' => 3, 'total' => 3])
            ->assertCreated()
            ->assertJsonPath('result.score', 3);

        $this->assertDatabaseHas('lesson_results', [
            'user_id' => $student->id,
            'lesson_key' => 'tilapia-guided-dissection',
            'score' => 3,
            'total' => 3,
        ]);
    }

    public function test_only_an_administrator_can_approve_an_instructor(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'status' => 'pending']);
        $student = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($student)
            ->postJson("/api/admin/users/{$instructor->id}/approve")
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$instructor->id}/approve")
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $instructor->id, 'status' => 'active']);
    }
}
