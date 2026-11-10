<?php

namespace App\Services;

use App\Models\SpeciesPackage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class GuidedClipDrafts
{
    public function __construct(private SpeciesPackageContent $content, private SpeciesPackageLifecycle $lifecycle, private SpeciesPackageImporter $importer) {}

    private function root(): string
    {
        $root = storage_path('app/private/guided-clip-drafts');
        File::ensureDirectoryExists($root, 0700);

        return $root;
    }

    private function authorize(SpeciesPackage $package, User $actor): void
    {
        abort_unless(in_array($actor->role, ['admin', 'instructor'], true) && $actor->status === 'active', 403);
        abort_unless($package->species->slug === 'rabbitfish' && in_array($package->status, ['draft', 'review', 'published'], true), 404);
        $this->content->authorize($actor, $package);
    }

    public function create(SpeciesPackage $package, User $actor): array
    {
        $this->authorize($package, $actor);
        $this->cleanup();
        $active = 0;
        foreach (glob($this->root().'/*/state') ?: [] as $state) {
            $d = $this->decode($state);
            if (($d['owner'] ?? null) === $actor->id && ! isset($d['committed'])) {
                $active++;
                if ($d['packageId'] === $package->id) {
                    return $this->read($package, $actor, basename(dirname($state)));
                }
            }
        }
        $this->check($active < 3, 'Finish or cancel another working draft before opening a fourth.');
        $manifest = $this->content->manifest($package->load('assets'));
        $steps = $manifest['contentData']['lesson']['steps'] ?? [];
        $this->check(count($steps) > 0 && isset($manifest['files'], $manifest['content']['lesson']), 'This lesson needs a complete source package before editing clips.');
        $id = bin2hex(random_bytes(24));
        File::ensureDirectoryExists($this->root().'/'.$id, 0700);
        touch($this->root().'/'.$id.'.lock');
        chmod($this->root().'/'.$id.'.lock', 0600);
        $d = ['owner' => $actor->id, 'packageId' => $package->id, 'expires' => now()->addHours(24)->timestamp, 'revision' => 1,
            'sourceFingerprint' => $this->lifecycle->contentFingerprint($package), 'originalSteps' => $steps,
            'sourceDepths' => $manifest['contentData']['lesson']['stepDepths'] ?? [], 'assets' => [], 'steps' => []];
        foreach ($steps as $i => $step) {
            $d['steps'][] = ['id' => 's'.$i, 'title' => $step['title'] ?? '', 'description' => $step['description'] ?? '',
                'prompt' => $step['prompt'] ?? 'Watch the clip, then continue.', 'action' => $step['action'] ?? 'Continue', 'videoAsset' => $step['videoAsset'] ?? null];
        }
        $this->persist($id, $d);

        return $this->payload($package, $id, $d);
    }

    public function read(SpeciesPackage $package, User $actor, string $id): array
    {
        return $this->access($package, $actor, $id, fn (&$d) => $this->payload($package, $id, $d));
    }

    private function access(SpeciesPackage $package, User $actor, string $id, callable $operation): mixed
    {
        $this->authorize($package, $actor);
        abort_unless(preg_match('/^[a-f0-9]{48}$/D', $id), 404);
        $dir = $this->root().'/'.$id;
        abort_unless(is_file($dir.'/state'), 410, 'This working draft expired or was cancelled. Open the editor again.');
        $handle = fopen($this->root().'/'.$id.'.lock', 'c+');
        try {
            flock($handle, LOCK_EX);
            $d = $this->decode($dir.'/state');
            abort_unless($d && $d['owner'] === $actor->id && $d['packageId'] === $package->id, 404);
            abort_if($d['expires'] <= now()->timestamp || isset($d['cancelled']), 410, 'This working draft expired or was cancelled.');
            $result = $operation($d);
            $this->persist($id, $d);
            foreach (glob($dir.'/*.mp4') ?: [] as $file) {
                if (isset($d['cancelled']) || isset($d['committed']) || ! isset($d['assets'][basename($file, '.mp4')])) {
                    @unlink($file);
                }
            }

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function decode(string $file): ?array
    {
        try {
            return json_decode(Crypt::decryptString(File::get($file)), true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
    }

    private function persist(string $id, array $draft): void
    {
        File::replace($this->root().'/'.$id.'/state', Crypt::encryptString(json_encode($draft, JSON_THROW_ON_ERROR)), 0600);
    }

    private function revision(array $draft, int $revision): void
    {
        abort_if(isset($draft['committed']), 409, 'This draft was already saved as a lesson version.');
        abort_unless($draft['revision'] === $revision, 409, 'This working draft changed in another tab. Close and reopen it before continuing.');
    }

    private function isClipOnly(array $step): bool
    {
        return ($step['kind'] ?? '') === 'preparation' && empty($step['targets']) && isset($step['videoAsset']);
    }

    public function update(SpeciesPackage $package, User $actor, string $id, array $input): array
    {
        return $this->access($package, $actor, $id, function (&$d) use ($package, $id, $input) {
            $this->revision($d, $input['revision']);
            $required = [];
            foreach ($d['originalSteps'] as $i => $step) {
                if (! $this->isClipOnly($step)) {
                    $required[] = 's'.$i;
                }
            }
            $seenCore = [];
            $allowedAssets = array_merge(array_keys($package->manifest['assets']), array_keys($d['assets']));
            foreach ($input['steps'] as $step) {
                if (str_starts_with($step['id'], 's')) {
                    $index = substr($step['id'], 1);
                    $this->check(ctype_digit($index) && isset($d['originalSteps'][(int) $index]) && $step['id'] === 's'.(int) $index, 'Choose a step from this source lesson.');
                    if (in_array($step['id'], $required, true)) {
                        $seenCore[] = $step['id'];
                    }
                }
                $key = $step['videoAsset'];
                if ($key !== null) {
                    $this->check(in_array($key, $allowedAssets, true), 'Choose a clip belonging to this working draft or source lesson.');
                    $path = $package->manifest['assets'][$key] ?? 'clip.mp4';
                    $this->check((bool) preg_match('/\.(mp4|webm)$/i', $path), 'A lesson clip must be a video.');
                }
            }
            $this->check($required === $seenCore, 'Keep every required anatomy and activity step in its original relative order.');
            $lastSource = array_key_last($d['originalSteps']);
            if (($d['originalSteps'][$lastSource]['kind'] ?? '') === 'quiz') {
                $this->check(end($input['steps'])['id'] === 's'.$lastSource, 'Keep the final knowledge check after every guided clip.');
            }
            $d['steps'] = $input['steps'];
            $d['revision']++;
            $this->prune($id, $d);

            return $this->payload($package, $id, $d);
        });
    }

    public function upload(SpeciesPackage $package, User $actor, string $id, array $input, GuidedClipVideo $video): array
    {
        return $this->access($package, $actor, $id, function (&$d) use ($package, $actor, $id, $input, $video) {
            $this->revision($d, $input['revision']);
            $index = array_search($input['stepId'], array_column($d['steps'], 'id'), true);
            $this->check($index !== false, 'Save the step before attaching its clip.');
            $key = 'clip-'.Str::uuid();
            $target = $this->root().'/'.$id.'/'.$key.'.mp4';
            try {
                $media = $video->prepare($input['asset'], $target);
                $this->authorize($package->fresh(), $actor->fresh());
                $d['assets'][$key] = [...$media, 'provenance' => [
                    'caption' => $input['caption'], 'source' => $input['source'], 'licence' => $input['licence'],
                    'aiGenerated' => (bool) $input['aiGenerated'], 'reviewStatus' => 'awaiting_faculty_review',
                    'personalMetadataRemoved' => true,
                ]];
                $d['steps'][$index]['videoAsset'] = $key;
                $this->check($this->size($package, $d) <= config('species-packages.max_archive_bytes'), 'The new lesson exceeds 250 MiB. Remove or replace draft clips before continuing.');
                $d['revision']++;
                $this->prune($id, $d);

                return $this->payload($package, $id, $d);
            } catch (\Throwable $e) {
                @unlink($target);
                throw $e;
            }
        });
    }

    public function asset(SpeciesPackage $package, User $actor, string $id, string $key): string
    {
        return $this->access($package, $actor, $id, function (&$d) use ($id, $key) {
            abort_unless(isset($d['assets'][$key]) && preg_match('/^clip-[a-f0-9-]{36}$/D', $key), 404);
            $path = $this->root().'/'.$id.'/'.$key.'.mp4';
            abort_unless(is_file($path) && hash_equals($d['assets'][$key]['sha256'], hash_file('sha256', $path)), 404);

            return $path;
        });
    }

    private function prune(string $id, array &$d): void
    {
        $used = array_column($d['steps'], 'videoAsset');
        foreach ($d['assets'] as $key => $media) {
            if (! in_array($key, $used, true)) {
                unset($d['assets'][$key]);
            }
        }
    }

    private function removedFiles(SpeciesPackage $package, array $d): array
    {
        $used = array_column($d['steps'], 'videoAsset');
        $removed = [];
        foreach (array_unique(array_filter(array_column($d['originalSteps'], 'videoAsset'))) as $key) {
            if ($key !== 'video' && ! in_array($key, $used, true)) {
                $removed[$key] = $package->manifest['assets'][$key];
            }
        }

        return $removed;
    }

    private function size(SpeciesPackage $package, array $d): int
    {
        $removed = array_values($this->removedFiles($package, $d));
        $total = 0;
        foreach ($package->assets as $asset) {
            if (! in_array($asset->relative_path, $removed, true)) {
                $total += $asset->size_bytes;
            }
        }

        return $total + array_sum(array_column($d['assets'], 'sizeBytes'));
    }

    private function payload(SpeciesPackage $package, string $id, array $d): array
    {
        $assets = [];
        foreach ($package->assets as $asset) {
            if (str_starts_with($asset->mime_type, 'video/')) {
                $assets[$asset->asset_key] = ['url' => url('/api/species-packages/'.$package->id.'/assets/'.$asset->id),
                    'caption' => $package->manifest['assetProvenance'][$asset->asset_key]['caption'] ?? '',
                    'source' => $package->manifest['assetProvenance'][$asset->asset_key]['source'] ?? '',
                    'licence' => $package->manifest['assetProvenance'][$asset->asset_key]['licence'] ?? '',
                    'aiGenerated' => $package->manifest['assetProvenance'][$asset->asset_key]['aiGenerated'] ?? false];
            }
        }
        foreach ($d['assets'] as $key => $asset) {
            $assets[$key] = [...$asset['provenance'], 'url' => url('/api/staff/species-packages/'.$package->id.'/clip-drafts/'.$id.'/assets/'.$key), 'durationSeconds' => $asset['durationSeconds']];
        }
        $steps = array_map(function ($s) use ($d) {
            $original = str_starts_with($s['id'], 's') ? ($d['originalSteps'][(int) substr($s['id'], 1)] ?? null) : null;

            return [...$s, 'clipOnly' => $original === null || $this->isClipOnly($original),
                'terminal' => ($original['kind'] ?? '') === 'quiz' && $s['id'] === 's'.array_key_last($d['originalSteps'])];
        }, $d['steps']);

        return ['draftId' => $id, 'revision' => $d['revision'], 'expiresAt' => $d['expires'], 'sourceVersion' => $package->version,
            'steps' => $steps, 'assets' => $assets, 'sizeBytes' => $this->size($package, $d),
            'maxPackageBytes' => config('species-packages.max_archive_bytes'), 'committedPackageId' => $d['committed'] ?? null];
    }

    public function commit(SpeciesPackage $package, User $actor, string $id, array $input): array
    {
        return $this->access($package, $actor, $id, function (&$d) use ($package, $actor, $id, $input) {
            $token = hash('sha256', $id);
            $saved = SpeciesPackage::where('species_id', $package->species_id)->where('imported_by', $actor->id)->where('manifest->clipRevisionId', $token)->first();
            if ($saved) {
                $this->auditSave($saved, $package, $actor, $d);
                $d['committed'] = $saved->id;

                return ['package' => ['id' => $saved->id, 'version' => $saved->version, 'status' => $saved->status]];
            }
            $this->revision($d, $input['revision']);
            try {
                $fingerprint = $this->lifecycle->contentFingerprint($package->fresh(['assets']));
            } catch (\RuntimeException) {
                $this->check(false, 'The source lesson failed integrity checks. Restore its files before saving this working draft.');
            }
            abort_unless(hash_equals($d['sourceFingerprint'], $fingerprint), 409, 'The source package changed. Open a fresh working draft.');
            $manifest = $package->manifest;
            $manifest['packageVersion'] = $input['version'];
            $manifest['revisionOf'] = ['packageId' => $package->id, 'version' => $package->version];
            $manifest['clipRevisionId'] = $token;
            $manifest['draftNotice'] = 'Faculty-authored instructions and guided clips require independent faculty review before student publication.';
            if (isset($manifest['viewer']['optional3d']['adapter'])) {
                unset($manifest['viewer']['optional3d']);
            }
            $lesson = $this->content->manifest($package)['contentData']['lesson'];
            $steps = [];
            $depths = [];
            $depth = 0;
            $clip = 0;
            foreach ($d['steps'] as $s) {
                $index = str_starts_with($s['id'], 's') ? (int) substr($s['id'], 1) : null;
                $original = $index !== null ? $d['originalSteps'][$index] : null;
                $clipOnly = $original === null || $this->isClipOnly($original);
                $this->check(! $clipOnly || $s['videoAsset'] !== null, 'Attach a clip to every clip-only step before saving the lesson.');
                $this->check(! $clipOnly || $s['description'] !== 'Faculty instructions to be completed.', 'Complete the faculty instructions for each new clip step before saving the lesson.');
                $step = $original ?? ['kind' => 'preparation', 'targets' => []];
                foreach (['title', 'description', 'prompt', 'action'] as $field) {
                    $step[$field] = $s[$field];
                }
                unset($step['videoAsset']);
                if ($s['videoAsset'] !== null) {
                    $step['videoAsset'] = $s['videoAsset'];
                }
                if ($clipOnly) {
                    $step['eyebrow'] = 'Guided clip '.str_pad((string) ++$clip, 2, '0', STR_PAD_LEFT);
                } else {
                    $depth = $d['sourceDepths'][$index] ?? 0;
                }
                $steps[] = $step;
                $depths[] = $depth;
            }
            $lesson['steps'] = $steps;
            $lesson['stepDepths'] = $depths;
            foreach ($this->removedFiles($package, $d) as $key => $path) {
                unset($manifest['assets'][$key], $manifest['assetProvenance'][$key]);
                if (! in_array($path, $manifest['assets'], true)) {
                    unset($manifest['files'][$path]);
                }
            }
            $lessonBytes = json_encode($lesson, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            $manifest['files'][$manifest['content']['lesson']] = hash('sha256', $lessonBytes);
            foreach ($d['assets'] as $key => $media) {
                $manifest['assets'][$key] = 'assets/'.$key.'.mp4';
                $manifest['files']['assets/'.$key.'.mp4'] = $media['sha256'];
                $manifest['assetProvenance'][$key] = $media['provenance'];
            }
            $temp = $this->root().'/'.$id.'/revision.aqpack';
            $zip = new ZipArchive;
            $this->check($zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'The lesson archive could not be prepared. Retry the save.');
            $closed = false;
            try {
                foreach ($manifest['files'] as $file => $hash) {
                    $key = array_search($file, $manifest['assets'], true);
                    if ($file === $manifest['content']['lesson']) {
                        $zip->addFromString($file, $lessonBytes);
                    } elseif ($key !== false && isset($d['assets'][$key])) {
                        $path = $this->root().'/'.$id.'/'.$key.'.mp4';
                        $this->check(is_file($path) && hash_equals($hash, hash_file('sha256', $path)), 'A staged clip failed integrity checks. Upload it again.');
                        $zip->addFile($path, $file);
                    } else {
                        $zip->addFile($this->content->absolutePath($package, $file), $file);
                    }
                }
                $zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                $zip->close();
                $closed = true;
                $this->check(filesize($temp) <= config('species-packages.max_archive_bytes'), 'The prepared lesson exceeds 250 MiB. Reduce the draft media size.');
                $this->authorize($package->fresh(), $actor->fresh());
                $saved = $this->importer->import(new UploadedFile($temp, 'guided-clips.aqpack', 'application/zip', null, true), $actor);
                $this->auditSave($saved, $package, $actor, $d);
                $d['committed'] = $saved->id;
                foreach ($d['assets'] as $key => $media) {
                    @unlink($this->root().'/'.$id.'/'.$key.'.mp4');
                }

                return ['package' => ['id' => $saved->id, 'version' => $saved->version, 'status' => $saved->status]];
            } finally {
                if (! $closed) {
                    $zip->close();
                }
                @unlink($temp);
            }
        });
    }

    private function auditSave(SpeciesPackage $saved, SpeciesPackage $source, User $actor, array $draft): void
    {
        $saved->events()->firstOrCreate(['event' => 'guided_clips_saved'], ['user_id' => $actor->id, 'metadata' => [
            'sourcePackageId' => $source->id, 'sourceFingerprint' => $draft['sourceFingerprint'],
            'sourceVersion' => $source->version, 'version' => $saved->version,
            'checksum' => $saved->checksum, 'clipCount' => count(array_filter(array_column($draft['steps'], 'videoAsset'))),
        ]]);
    }

    public function cancel(SpeciesPackage $package, User $actor, string $id): array
    {
        return $this->access($package, $actor, $id, function (&$d) use ($id) {
            $d['cancelled'] = true;
            foreach (glob($this->root().'/'.$id.'/*.mp4') ?: [] as $file) {
                @unlink($file);
            }

            return ['cancelled' => true];
        });
    }

    public function cleanup(): int
    {
        $root = storage_path('app/private/guided-clip-drafts');
        if (! is_dir($root)) {
            return 0;
        }
        $count = 0;
        foreach (glob($root.'/*/state') ?: [] as $file) {
            $id = basename(dirname($file));
            if (! preg_match('/^[a-f0-9]{48}$/D', $id)) {
                continue;
            }
            $handle = fopen($this->root().'/'.$id.'.lock', 'c+');
            if (! flock($handle, LOCK_EX | LOCK_NB)) {
                fclose($handle);

                continue;
            }
            try {
                $d = $this->decode($file);
                if (($d['expires'] ?? 0) <= now()->timestamp || isset($d['cancelled'])) {
                    File::deleteDirectory(dirname($file));
                    $count++;
                }
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
        foreach (glob($root.'/*.lock') ?: [] as $file) {
            if (! is_dir(substr($file, 0, -5)) && filemtime($file) < now()->subDay()->timestamp) {
                @unlink($file);
            }
        }

        return $count;
    }

    private function check(bool $ok, string $message): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['clips' => $message]);
        }
    }
}
