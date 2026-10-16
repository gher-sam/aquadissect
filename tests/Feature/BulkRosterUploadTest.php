<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\RosterEvent;
use App\Models\User;
use App\Services\RosterFileReader;
use App\Services\RosterUploadDrafts;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class BulkRosterUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        $this->travel(31)->minutes();
        app(RosterUploadDrafts::class)->cleanup();
        $this->travelBack();
        parent::tearDown();
    }

    private function offering(string $year = '2026-2027', string $section = 'A'): LearningClass
    {
        return LearningClass::create(['course_id' => LearningClass::first()->course_id, 'code' => 'SYNTHETIC-'.$year.'-'.$section, 'name' => 'Synthetic section '.$section, 'academic_year' => $year, 'semester' => '1', 'section_code' => $section, 'status' => 'active']);
    }

    private function upload(string $content = "StudentNo,First Name,Surname,Email,Year Level,Home Section\n0001-DEMO,Zoë,試験,zoe@example.test,2,Irregular\n0002-DEMO,Other,Learner,other@example.test,4,B\n"): array
    {
        return $this->post('/api/admin/roster/uploads', ['roster' => UploadedFile::fake()->createWithContent('synthetic.csv', $content)], ['Accept' => 'application/json'])->assertOk()->json();
    }

    private function map(array $draft): array
    {
        return $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/map', ['revision' => $draft['revision'], 'sheet' => 0, 'headerRow' => 1, 'mapping' => ['identityNumber' => 0, 'firstName' => 1, 'lastName' => 2, 'email' => 3, 'yearLevel' => 4, 'homeSection' => 5]])->assertOk()->json();
    }

    private function review(array $draft, array $mapped, array $selected): array
    {
        return $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/review', ['revision' => $mapped['revision'], 'selected' => $selected])->assertOk()->json();
    }

    public function test_selection_multiple_sections_repeat_and_later_year_reuse_preserve_accounts(): void
    {
        $a = $this->offering();
        $b = $this->offering(section: 'B');
        $draft = $this->upload();
        $mapped = $this->map($draft);
        $before = User::count();
        $review = $this->review($draft, $mapped, [['id' => 2, 'email' => 'zoe@example.test', 'offeringId' => $a->id], ['id' => 3, 'email' => 'other@example.test', 'offeringId' => $b->id]]);
        $this->assertSame($before, User::count());
        $this->assertCount(2, $review['destinations']);
        $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/import', ['revision' => $review['revision']])->assertOk()->assertJsonPath('counts.new', 2);
        $student = User::where('identity_number', '0001-DEMO')->firstOrFail();
        $original = $student->getAttributes();
        $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/import', ['revision' => $review['revision']])->assertGone();
        foreach ([$a, $this->offering('2027-2028')] as $destination) {
            $draft = $this->upload("StudentNo,First Name,Surname,Email\n0001-DEMO,Zoë,試験,\n");
            $mapped = $this->map($draft);
            $this->assertTrue($mapped['rows'][0]['emailSuggested']);
            $review = $this->review($draft, $mapped, [['id' => 2, 'email' => $mapped['rows'][0]['email'], 'offeringId' => $destination->id]]);
            $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/import', ['revision' => $review['revision']])->assertOk()->assertJsonPath('counts.new', 0);
        }
        $this->assertSame($original, $student->fresh()->getAttributes());
        $this->assertSame(2, $student->classEnrollments()->count());
        $event = RosterEvent::where('action', 'bulk_roster_imported')->first();
        $this->assertSame('csv', $event->details['source']);
        $this->assertArrayNotHasKey('activationCodes', $event->details);
    }

    public function test_cancellation_exclusion_owner_expiry_and_access_controls(): void
    {
        $count = User::count();
        $draft = $this->upload();
        $mapped = $this->map($draft);
        $other = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($other)->deleteJson('/api/admin/roster/uploads/'.$draft['draftId'])->assertNotFound();
        $this->actingAs($this->admin)->deleteJson('/api/admin/roster/uploads/'.$draft['draftId'])->assertOk();
        $this->assertSame($count + 1, User::count());
        $draft = $this->upload();
        $this->travel(31)->minutes();
        $this->mapExpired($draft);
        $this->travelBack();
        $this->actingAs(User::where('role', 'student')->first())->postJson('/api/admin/roster/uploads')->assertForbidden();
        auth()->logout();
        $this->postJson('/api/admin/roster/uploads')->assertUnauthorized();
    }

    private function mapExpired(array $draft): void
    {
        $this->deleteJson('/api/admin/roster/uploads/'.$draft['draftId'])->assertGone();
    }

    public function test_blank_emails_create_distinct_pending_students_without_activation_tokens_and_repeat_safely(): void
    {
        $a = $this->offering();
        $b = $this->offering(section: 'B');
        $draft = $this->upload("StudentNo,First Name,Surname,Email\n0001-DEMO,Zoë,試験,\n0002-DEMO,Other,Learner,\n");
        $mapped = $this->map($draft);
        $review = $this->review($draft, $mapped, [['id' => 2, 'email' => '', 'offeringId' => $a->id], ['id' => 3, 'email' => null, 'offeringId' => $b->id]]);
        $this->assertSame(0, $review['counts']['errors']);
        $this->assertSame(2, $review['counts']['new']);
        $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/import', ['revision' => $review['revision']])
            ->assertOk()->assertJsonPath('counts.new', 2)->assertJsonPath('activationCodes', []);
        foreach (['0001-DEMO' => $a, '0002-DEMO' => $b] as $id => $section) {
            $student = User::where('identity_number', $id)->firstOrFail();
            $this->assertNull($student->email);
            $this->assertSame('pending', $student->status);
            $this->assertDatabaseHas('class_enrollments', ['user_id' => $student->id, 'class_id' => $section->id, 'status' => 'active']);
            $this->assertDatabaseMissing('account_activation_tokens', ['user_id' => $student->id]);
            $this->postJson('/api/admin/students/'.$student->id.'/activation')->assertUnprocessable();
        }
        $draft = $this->upload("StudentNo,First Name,Surname,Email\n0001-DEMO,Zoë,試験,\n");
        $mapped = $this->map($draft);
        $this->assertSame('', $mapped['rows'][0]['email']);
        $this->assertFalse($mapped['rows'][0]['emailSuggested']);
        $review = $this->review($draft, $mapped, [['id' => 2, 'email' => '', 'offeringId' => $a->id]]);
        $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/import', ['revision' => $review['revision']])
            ->assertOk()->assertJsonPath('counts.new', 0)->assertJsonPath('counts.already_enrolled', 1);
    }

    public function test_id_only_enrollment_preserves_existing_account_and_rejects_wrong_names_and_staff_ids(): void
    {
        $a = $this->offering();
        $student = User::where('role', 'student')->firstOrFail();
        $before = $student->getAttributes();
        $draft = $this->upload("StudentNo,First Name,Surname,Email\n{$student->identity_number},{$student->first_name},{$student->last_name},\n");
        $mapped = $this->map($draft);
        $review = $this->review($draft, $mapped, [['id' => 2, 'email' => '', 'offeringId' => $a->id]]);
        $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/import', ['revision' => $review['revision']])
            ->assertOk()->assertJsonPath('counts.existing_student', 1);
        $this->assertSame($before, $student->fresh()->getAttributes());
        foreach ([$student, $this->admin] as $existing) {
            $draft = $this->upload("StudentNo,First Name,Surname,Email\n{$existing->identity_number},Wrong,Name,\n");
            $mapped = $this->map($draft);
            $review = $this->review($draft, $mapped, [['id' => 2, 'email' => '', 'offeringId' => $a->id]]);
            $this->assertSame(1, $review['counts']['errors']);
            $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/import', ['revision' => $review['revision']])->assertUnprocessable();
        }
    }

    public function test_invalid_email_duplicates_account_conflicts_and_stale_reviews_do_not_write(): void
    {
        $a = $this->offering();
        $draft = $this->upload();
        $mapped = $this->map($draft);
        $review = $this->review($draft, $mapped, [['id' => 2, 'email' => 'invalid-address', 'offeringId' => $a->id]]);
        $this->assertSame(1, $review['counts']['errors']);
        $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/import', ['revision' => $review['revision']])->assertUnprocessable();
        $review = $this->review($draft, $review, [['id' => 2, 'email' => 'zoe@example.test', 'offeringId' => $a->id]]);
        $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/import', ['revision' => $mapped['revision']])->assertUnprocessable();
        $a->update(['status' => 'archived']);
        $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/import', ['revision' => $review['revision']])->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['identity_number' => '0001-DEMO']);
        $a->update(['status' => 'active']);
        $review = $this->review($draft, $review, [['id' => 2, 'email' => 'same@example.test', 'offeringId' => $a->id], ['id' => 3, 'email' => 'same@example.test', 'offeringId' => $a->id]]);
        $this->assertSame(1, $review['counts']['errors']);
        $existing = User::where('role', 'student')->first();
        $review = $this->review($draft, $review, [['id' => 2, 'email' => $existing->email, 'offeringId' => $a->id]]);
        $this->assertSame(1, $review['counts']['errors']);
    }

    public function test_concurrent_enrollment_change_requires_review_and_failed_second_section_rolls_back(): void
    {
        $a = $this->offering();
        $b = $this->offering(section: 'B');
        $draft = $this->upload();
        $mapped = $this->map($draft);
        $review = $this->review($draft, $mapped, [['id' => 2, 'email' => 'zoe@example.test', 'offeringId' => $a->id], ['id' => 3, 'email' => 'other@example.test', 'offeringId' => $b->id]]);
        DB::statement("CREATE TRIGGER synthetic_failure BEFORE INSERT ON class_enrollments WHEN NEW.class_id = {$b->id} BEGIN SELECT RAISE(ABORT, 'synthetic rollback'); END");
        $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/import', ['revision' => $review['revision']])->assertStatus(500);
        $this->assertDatabaseMissing('users', ['identity_number' => '0001-DEMO']);
        $this->assertDatabaseMissing('users', ['identity_number' => '0002-DEMO']);
        DB::statement('DROP TRIGGER synthetic_failure');
        $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/import', ['revision' => $review['revision']])->assertOk();
        $draft = $this->upload();
        $mapped = $this->map($draft);
        $review = $this->review($draft, $mapped, [['id' => 2, 'email' => 'zoe@example.test', 'offeringId' => $a->id]]);
        ClassEnrollment::where('class_id', $a->id)->where('user_id', User::where('identity_number', '0001-DEMO')->value('id'))->update(['status' => 'inactive']);
        $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/import', ['revision' => $review['revision']])->assertUnprocessable();
    }

    public function test_supported_readers_preserve_unicode_and_text_identifiers(): void
    {
        $reader = app(RosterFileReader::class);
        foreach (['csv' => "StudentNo,First Name,Surname\n0001-DEMO,Zoë,試験\n", 'tsv' => "StudentNo\tFirst Name\tSurname\n0001-DEMO\tZoë\t試験\n", 'json' => '[{"StudentNo":"0001-DEMO","First Name":"Zoë","Surname":"試験"}]', 'xml' => '<students><student StudentNo="0001-DEMO"><FirstName>Zoë</FirstName><Surname>試験</Surname></student></students>'] as $format => $content) {
            $data = $reader->inspect(UploadedFile::fake()->createWithContent('synthetic.'.$format, $content));
            $this->assertSame(['0001-DEMO', 'Zoë', '試験'], $data['sheets'][0]['rows'][1]);
        }
        foreach (['Xlsx', 'Xls'] as $type) {
            $book = new Spreadsheet;
            $book->getActiveSheet()->setTitle('Cover')->setCellValue('A1', 'Synthetic cover');
            $sheet = $book->createSheet()->setTitle('Students');
            $sheet->fromArray([['Synthetic title'], ['StudentNo', 'First Name', 'Surname'], ['0001-DEMO', 'Zoë', '試験']]);
            $sheet->setCellValueExplicit('A3', '0001-DEMO', DataType::TYPE_STRING);
            $path = tempnam(sys_get_temp_dir(), 'roster');
            try {
                IOFactory::createWriter($book, $type)->save($path);
                $data = $reader->inspect(new UploadedFile($path, 'synthetic.'.strtolower($type), test: true));
                $this->assertSame(['0001-DEMO', 'Zoë', '試験'], $data['sheets'][1]['rows'][2]);
                $draft = app(RosterUploadDrafts::class)->create($data, $this->admin);
                $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/map', ['revision' => 1, 'sheet' => 1, 'headerRow' => 2, 'mapping' => ['identityNumber' => 0, 'firstName' => 1, 'lastName' => 2]])->assertOk()->assertJsonPath('rows.0.identityNumber', '0001-DEMO');
            } finally {
                @unlink($path);
                $book->disconnectWorksheets();
            }
        }
        $xml = '<?xml version="1.0"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Students"><Table><Row><Cell><Data ss:Type="String">StudentNo</Data></Cell><Cell><Data ss:Type="String">FirstName</Data></Cell><Cell><Data ss:Type="String">Surname</Data></Cell></Row><Row><Cell><Data ss:Type="String">0001-DEMO</Data></Cell><Cell><Data ss:Type="String">Zoë</Data></Cell><Cell><Data ss:Type="String">試験</Data></Cell></Row></Table></Worksheet></Workbook>';
        $data = $reader->inspect(UploadedFile::fake()->createWithContent('synthetic.xml', $xml));
        $this->assertSame(['0001-DEMO', 'Zoë', '試験'], $data['sheets'][0]['rows'][1]);
    }

    public function test_rejects_malformed_entities_oversize_and_formulas(): void
    {
        foreach (['xml' => '<!DOCTYPE students [<!ENTITY x SYSTEM "file:///private">]><students>&x;</students>', 'json' => '{broken', 'pdf' => 'synthetic', 'csv' => str_repeat("a,b,c\n", 1101)] as $format => $content) {
            $this->post('/api/admin/roster/uploads', ['roster' => UploadedFile::fake()->createWithContent('synthetic.'.$format, $content)], ['Accept' => 'application/json'])->assertUnprocessable();
        }
        $this->post('/api/admin/roster/uploads', ['roster' => UploadedFile::fake()->create('synthetic.csv', 5121)], ['Accept' => 'application/json'])->assertUnprocessable();
        $book = new Spreadsheet;
        $book->getActiveSheet()->setCellValue('A1', '=1+1');
        $path = tempnam(sys_get_temp_dir(), 'roster');
        try {
            IOFactory::createWriter($book, 'Xlsx')->save($path);
            $this->post('/api/admin/roster/uploads', ['roster' => new UploadedFile($path, 'synthetic.xlsx', test: true)], ['Accept' => 'application/json'])->assertUnprocessable();
        } finally {
            @unlink($path);
        }
    }

    public function test_selected_subset_uses_delayed_clone_and_retains_unselected_rows(): void
    {
        config(['delayed-clone.enabled' => true, 'delayed-clone.delay_hours' => 12]);
        config()->set('database.connections.sqlite_clone.database', ':memory:');
        DB::purge('sqlite_clone');
        \Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--database' => 'sqlite_clone', '--force' => true]);
        $a = $this->offering();
        app(\App\Services\CloneRefresher::class)->refresh();
        $draft = $this->upload();
        $mapped = $this->map($draft);
        $review = $this->review($draft, $mapped, [['id' => 2, 'email' => 'zoe@example.test', 'offeringId' => $a->id]]);
        $this->postJson('/api/admin/roster/uploads/'.$draft['draftId'].'/import', ['revision' => $review['revision']])->assertOk();
        $this->assertDatabaseMissing('users', ['identity_number' => '0002-DEMO']);
        $this->assertFalse(DB::connection('sqlite_clone')->table('users')->where('identity_number', '0001-DEMO')->exists());
        $this->travel(12)->hours();
        $report = app(\App\Services\DelayedCloneApplier::class)->applyEligible();
        $this->assertSame(0, $report['failed']);
        $this->assertTrue(DB::connection('sqlite_clone')->table('users')->where('identity_number', '0001-DEMO')->exists());
        $this->travelBack();
    }
}
