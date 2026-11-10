<?php

namespace Tests\Feature;

use App\Services\GuidedClipVideo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GuidedClipVideoTest extends TestCase
{
    public function test_real_processing_removes_audio_and_metadata_and_rejects_long_or_corrupt_input(): void
    {
        $ffmpeg = getenv('AQUADISSECT_FFMPEG_BINARY');
        $ffprobe = getenv('AQUADISSECT_FFPROBE_BINARY');
        if (! $ffmpeg || ! $ffprobe) {
            $this->markTestSkipped('Set configured FFmpeg and FFprobe paths to run the real media-processing check.');
        }
        config(['species-packages.ffmpeg_binary' => $ffmpeg, 'species-packages.ffprobe_binary' => $ffprobe]);
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'aqd-video-'.Str::uuid();
        File::ensureDirectoryExists($dir);
        try {
            $valid = $dir.'/source.mp4';
            $long = $dir.'/long.mp4';
            (new Process([$ffmpeg, '-nostdin', '-v', 'error', '-f', 'lavfi', '-i', 'testsrc2=size=640x360:rate=24', '-f', 'lavfi', '-i', 'sine=frequency=440', '-t', '10', '-c:v', 'libx264', '-c:a', 'aac', '-metadata', 'comment=REMOVE_TEST_METADATA', $valid]))->setTimeout(60)->mustRun();
            (new Process([$ffmpeg, '-nostdin', '-v', 'error', '-f', 'lavfi', '-i', 'testsrc2=size=320x240:rate=24', '-t', '10.5', '-c:v', 'libx264', $long]))->setTimeout(60)->mustRun();
            $service = app(GuidedClipVideo::class);
            $result = $service->prepare(new UploadedFile($valid, 'input.mp4', 'video/mp4', null, true), $dir.'/output.mp4');
            $this->assertLessThanOrEqual(10, $result['durationSeconds']);
            $this->assertLessThanOrEqual(3 * 1024 * 1024, $result['sizeBytes']);
            $probe = new Process([$ffprobe, '-v', 'error', '-show_format', '-show_streams', '-of', 'json', $dir.'/output.mp4']);
            $probe->mustRun();
            $info = json_decode($probe->getOutput(), true);
            $this->assertCount(1, $info['streams']);
            $this->assertSame('h264', $info['streams'][0]['codec_name']);
            $this->assertLessThanOrEqual(1280, $info['streams'][0]['width']);
            $this->assertLessThanOrEqual(720, $info['streams'][0]['height']);
            $this->assertStringNotContainsString('REMOVE_TEST_METADATA', $probe->getOutput());
            foreach ([['portrait.webm', '360x640', '1', 'libvpx-vp9', 360 / 640], ['anamorphic.mp4', '720x576', '2', 'libx264', 2.5]] as [$name, $size, $sar, $codec, $ratio]) {
                (new Process([$ffmpeg, '-nostdin', '-v', 'error', '-f', 'lavfi', '-i', 'testsrc2=size='.$size.':rate=24', '-t', '1', '-vf', 'setsar='.$sar, '-c:v', $codec, $dir.'/'.$name]))->setTimeout(60)->mustRun();
                $service->prepare(new UploadedFile($dir.'/'.$name, $name, null, null, true), $dir.'/aspect.mp4');
                $probe = new Process([$ffprobe, '-v', 'error', '-show_streams', '-of', 'json', $dir.'/aspect.mp4']);
                $probe->mustRun();
                $stream = json_decode($probe->getOutput(), true)['streams'][0];
                $this->assertEqualsWithDelta($ratio, $stream['width'] / $stream['height'], 0.01);
                $this->assertSame('1:1', $stream['sample_aspect_ratio']);
                $this->assertLessThanOrEqual(720, $stream['height']);
                $this->assertLessThanOrEqual(1280, $stream['width']);
            }
            file_put_contents($dir.'/corrupt.mp4', 'Synthetic invalid MP4');
            foreach ([$long, $dir.'/corrupt.mp4'] as $input) {
                try {
                    $service->prepare(new UploadedFile($input, 'input.mp4', 'video/mp4', null, true), $dir.'/rejected.mp4');
                    $this->fail('Invalid media must be rejected.');
                } catch (ValidationException $e) {
                    $this->assertArrayHasKey('asset', $e->errors());
                    $this->assertFileDoesNotExist($dir.'/rejected.mp4');
                }
            }
            config(['species-packages.ffprobe_binary' => $dir.'/unavailable']);
            $this->expectException(ValidationException::class);
            $service->prepare(new UploadedFile($valid, 'input.mp4', 'video/mp4', null, true), $dir.'/unavailable.mp4');
        } finally {
            if (str_starts_with($dir, sys_get_temp_dir().DIRECTORY_SEPARATOR.'aqd-video-')) {
                File::deleteDirectory($dir);
            }
        }
    }
}
