<?php

namespace Database\Seeders;

use App\Models\LearningModule;
use App\Models\LearningModuleVersion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LearningModuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['0.1.0', '0.2.0'] as $release) {
            $path = resource_path("learning-modules/lab-safety-tools/{$release}/manifest.json");
            $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

            DB::transaction(function () use ($manifest, $path): void {
                $identity = $manifest['module'];
                $module = LearningModule::query()->updateOrCreate(
                    ['slug' => $identity['slug']],
                    [
                        'title' => $identity['title'],
                        'category' => $identity['category'],
                        'description' => $identity['description'],
                    ],
                );

                $version = LearningModuleVersion::query()->where([
                    'learning_module_id' => $module->id,
                    'version' => $manifest['moduleVersion'],
                ])->first();
                if ($version) {
                    return;
                }

                $version = $module->versions()->create([
                    'version' => $manifest['moduleVersion'],
                    'status' => LearningModuleVersion::STATUS_DRAFT,
                    'manifest' => $manifest,
                    'is_current' => false,
                    'imported_by' => User::query()->where('role', User::ROLE_ADMIN)->value('id'),
                ]);
                $disk = Storage::disk(config('learning-modules.disk'));
                foreach ($manifest['assets'] ?? [] as $asset) {
                    $bytes = (string) file_get_contents(dirname($path).'/'.$asset['file']);
                    $relative = trim(config('learning-modules.root'), '/').'/'.$module->slug.'/'.$version->version.'/'.$asset['file'];
                    if (! $disk->put($relative, $bytes)) {
                        throw new \RuntimeException('Unable to install the bundled learning-module illustration.');
                    }
                    $version->assets()->create([
                        'asset_key' => $asset['key'],
                        'relative_path' => $relative,
                        'mime_type' => $asset['mimeType'],
                        'size_bytes' => strlen($bytes),
                        'sha256' => hash('sha256', $bytes),
                        'metadata' => $asset['metadata'],
                    ]);
                }
            });
        }
    }
}
