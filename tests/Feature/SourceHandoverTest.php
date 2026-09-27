<?php
namespace Tests\Feature;

use App\Models\User;
use App\Services\SourceRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class SourceHandoverTest extends TestCase
{
    use RefreshDatabase;
    private SourceRelease $release;
    protected function setUp(): void
    {
        parent::setUp();
        $this->release = new class extends SourceRelease {
            public function directory(): string { return storage_path('framework/testing/source-handover'); }
        };
        $this->app->instance(SourceRelease::class,$this->release);
        $dir=$this->release->directory(); if(!is_dir($dir)) mkdir($dir,0750,true);
        $revision=str_repeat('a',40); $zip=new ZipArchive;
        $zip->open($dir.'/'.$revision.'.zip',ZipArchive::CREATE|ZipArchive::OVERWRITE);
        $zip->addFromString('app/Example.php','<?php echo "<script>test</script>";');
        $zip->addFromString('public/species/sample.png',"\0binary");
        $zip->close();
        file_put_contents($dir.'/current.json',json_encode(['revision'=>$revision,'sha256'=>hash_file('sha256',$dir.'/'.$revision.'.zip'),'bytes'=>filesize($dir.'/'.$revision.'.zip'),'files'=>[['path'=>'app/Example.php','bytes'=>35],['path'=>'public/species/sample.png','bytes'=>7]]]));
    }
    protected function tearDown(): void
    {
        foreach(glob($this->release->directory().'/*') as $file) if(is_file($file)) unlink($file);
        parent::tearDown();
    }
    public function test_only_active_admins_can_access_all_source_endpoints(): void
    {
        $this->get('/source-handover')->assertRedirect('/login');
        $paths=['/api/admin/source-handover','/api/admin/source-handover/preview?path=app/Example.php','/api/admin/source-handover/file?path=app/Example.php','/api/admin/source-handover/download'];
        foreach($paths as $path) $this->getJson($path)->assertUnauthorized();
        foreach(['student','instructor'] as $role) {
            $this->actingAs(User::factory()->create(['role'=>$role,'status'=>'active']));
            $this->get('/source-handover')->assertForbidden();
            foreach($paths as $path) $this->getJson($path)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role'=>'admin','status'=>'pending']));
        foreach($paths as $path) $this->getJson($path)->assertForbidden();
    }
    public function test_admin_downloads_and_previews_share_a_private_allowlist(): void
    {
        $user=User::factory()->create(['role'=>'admin','status'=>'active']); $this->actingAs($user);
        $this->get('/source-handover')->assertOk();
        $this->getJson('/api/admin/source-handover')->assertOk()->assertJsonCount(2,'files');
        $this->getJson('/api/admin/source-handover/preview?path=app/Example.php')->assertOk()->assertJsonPath('content','<?php echo "<script>test</script>";');
        foreach(['../.env','.env','storage/logs/laravel.log','app/Missing.php','/etc/passwd','app/../config/app.php'] as $path) {
            $this->getJson('/api/admin/source-handover/preview?path='.urlencode($path))->assertNotFound();
            $this->getJson('/api/admin/source-handover/file?path='.urlencode($path))->assertNotFound();
        }
        $this->getJson('/api/admin/source-handover/preview?path=public/species/sample.png')->assertUnprocessable();
        $file=$this->get('/api/admin/source-handover/file?path=app/Example.php')->assertOk();
        $this->assertStringContainsString('no-store',$file->headers->get('Cache-Control'));
        $this->get('/api/admin/source-handover/download')->assertOk()->assertDownload('AquaDissect-source-aaaaaaaaaaaa.zip');
        $lines=file($this->release->directory().'/downloads.jsonl'); $this->assertCount(2,$lines);
        $this->assertSame($user->id,json_decode($lines[0],true)['user_id']);
        $this->assertStringNotContainsString($user->email,implode('',$lines));
    }
    public function test_allowlist_and_corruption_fail_closed(): void
    {
        foreach(['.env','.git/config','bootstrap/cache/config.php','database/database.sqlite','public/deploy-hook.php','resources/views/.secret.php','deliverables/report.docx','app/link/../../.env','scripts/deployment/deploy.sh'] as $path) $this->assertFalse(SourceRelease::allowed($path),$path);
        $this->assertTrue(SourceRelease::allowed('app/Models/User.php'));
        $this->actingAs(User::factory()->create(['role'=>'admin','status'=>'active']));
        file_put_contents($this->release->directory().'/'.str_repeat('a',40).'.zip','corrupt');
        $this->get('/api/admin/source-handover/download')->assertStatus(503);
        unlink($this->release->directory().'/current.json');
        $this->getJson('/api/admin/source-handover')->assertStatus(503);
    }
}
