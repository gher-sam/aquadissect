<?php
namespace App\Console\Commands;

use App\Services\SourceRelease;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use ZipArchive;

class BuildSourceRelease extends Command
{
    protected $signature = 'source:build';
    protected $description = 'Prepare an allowlisted source handover from the committed release';
    public function handle(SourceRelease $release): int
    {
        $run = function (array $args): string {
            $p = new Process(array_merge(['git'], $args), base_path());
            $p->setTimeout(180); $p->mustRun(); return trim($p->getOutput());
        };
        $revision = $run(['rev-parse','HEAD']);
        if (!preg_match('/^[a-f0-9]{40}$/D', $revision)) throw new \RuntimeException('Invalid release revision.');
        $dir = $release->directory();
        if (!is_dir($dir)) mkdir($dir, 0750, true);
        $temp = $dir.'/'.bin2hex(random_bytes(8)).'.tmp';
        $zip = new ZipArchive;
        try {
            // Archive only the immutable committed tree, never live filesystem contents.
            $run(['archive','--format=zip','--output='.$temp,$revision]);
            if ($zip->open($temp) !== true) throw new \RuntimeException('Unable to open release archive.');
            for ($i=$zip->numFiles-1; $i>=0; $i--) {
                $name=$zip->getNameIndex($i);
                $zip->getExternalAttributesIndex($i, $opsys, $attr);
                $symlink=(($attr >> 16) & 0170000) === 0120000;
                if ($symlink || !SourceRelease::allowed($name)) { $zip->deleteIndex($i); continue; }
                $data=$zip->getFromIndex($i);
                if (preg_match('/\.(php|js|json|md|css|scss|svg|mjs)$/', $name) && preg_match('/-----BEGIN (?:RSA |OPENSSH |EC )?PRIVATE KEY-----|gh[pousr]_[A-Za-z0-9]{30,}|AKIA[A-Z0-9]{16}/', $data)) throw new \RuntimeException('Credential pattern detected in approved source; release blocked.');
            }
            $zip->addFromString('HANDOVER.md', $run(['show',$revision.':resources/handover/README.md']));
            $zip->addFromString('.env.example', "APP_NAME=AquaDissect\nAPP_ENV=local\nAPP_KEY=\nAPP_DEBUG=true\nAPP_URL=http://127.0.0.1:8000\nDB_CONNECTION=sqlite\nCLONE_ENABLED=false\nSESSION_DRIVER=file\nCACHE_STORE=file\nQUEUE_CONNECTION=sync\nMAIL_MAILER=log\n");
            $zip->addFromString('public/.htaccess', $run(['show',$revision.':public/.htaccess']));
            $zip->close();
            $zip->open($temp); $files=[];
            for($i=0;$i<$zip->numFiles;$i++) { $s=$zip->statIndex($i); $files[]=['path'=>$s['name'],'bytes'=>$s['size']]; }
            $zip->close(); usort($files,fn($a,$b)=>strcmp($a['path'],$b['path']));
            $manifest=['revision'=>$revision,'preparedAt'=>now()->toIso8601String(),'sha256'=>hash_file('sha256',$temp),'bytes'=>filesize($temp),'files'=>$files];
            $archive=$dir.'/'.$revision.'.zip';
            if (!rename($temp,$archive)) throw new \RuntimeException('Unable to publish source archive.');
            $pointer=$dir.'/'.bin2hex(random_bytes(8)).'.json';
            file_put_contents($pointer,json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
            if (!rename($pointer,$dir.'/current.json')) throw new \RuntimeException('Unable to publish source manifest.');
            $this->info('Prepared source release '.$revision.' with '.count($files).' files.');
            return self::SUCCESS;
        } finally { if (is_file($temp)) unlink($temp); }
    }
}
