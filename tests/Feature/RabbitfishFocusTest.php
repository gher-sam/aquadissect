<?php

namespace Tests\Feature;

use App\Models\SpeciesPackage;
use App\Models\SpeciesAssignment;
use App\Models\User;
use App\Support\ClassPresenter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RabbitfishFocusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_default_staff_catalog_shows_only_rabbitfish_without_deleting_packages(): void
    {
        $this->assertSame(['rabbitfish'], config('species-packages.visible_slugs'));
        $before = SpeciesPackage::count();
        foreach (['admin', 'instructor'] as $role) {
            $user = User::query()->where('role', $role)->firstOrFail();
            $response = $this->actingAs($user)->getJson('/api/species')->assertOk();
            $this->assertNotEmpty($response->json('species'));
            $this->assertSame(['rabbitfish'], array_values(array_unique(array_column($response->json('species'), 'slug'))));
        }
        $this->assertSame($before, SpeciesPackage::count());
        $this->assertTrue(SpeciesPackage::whereHas('species', fn ($query) => $query->where('slug', 'tilapia'))->exists());
    }

    public function test_focus_does_not_publish_review_content_or_erase_class_assignments(): void
    {
        $student = User::query()->where('role', 'student')->firstOrFail();
        $rabbitfish = SpeciesPackage::whereHas('species', fn ($query) => $query->where('slug', 'rabbitfish'))->firstOrFail();
        $before = SpeciesAssignment::count();
        $this->actingAs($student)->getJson('/api/species')->assertOk()->assertJsonCount(0, 'species');
        $this->getJson('/api/species/rabbitfish/packages/'.$rabbitfish->id.'/manifest')->assertForbidden();
        $classes = app(ClassPresenter::class)->for($student);
        foreach ($classes as $class) {
            $this->assertSame([], $class['assignments']);
        }
        $this->assertGreaterThan(0, $before);
        $this->assertSame($before, SpeciesAssignment::count());
        $this->assertSame('review', $rabbitfish->fresh()->status);
    }

    public function test_guest_pages_use_rabbitfish_focus_without_draft_anatomy(): void
    {
        foreach (['/login', '/activate', '/instructor-registration'] as $url) {
            $this->get($url)->assertOk()->assertSee('Rabbitfish (danggit)')->assertDontSee('Tilapia')->assertDontSee('/species/tilapia/')->assertDontSee('/species/rabbitfish/');
        }
    }

    public function test_retained_catalog_can_be_restored_without_reseeding(): void
    {
        config(['species-packages.visible_slugs' => []]);
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $response = $this->actingAs($admin)->getJson('/api/species')->assertOk();
        $this->assertContains('tilapia', array_column($response->json('species'), 'slug'));
        $this->assertContains('rabbitfish', array_column($response->json('species'), 'slug'));
    }
}
