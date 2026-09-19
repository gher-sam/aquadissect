<?php

namespace Database\Seeders;

use App\Models\LearningModule;
use App\Models\LearningModuleVersion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LearningModuleSeeder extends Seeder
{
    public function run(): void
    {
        $path = resource_path('learning-modules/lab-safety-tools/0.1.0/manifest.json');
        $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($manifest): void {
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

            $module->versions()->create([
                'version' => $manifest['moduleVersion'],
                'status' => LearningModuleVersion::STATUS_DRAFT,
                'manifest' => $manifest,
                'is_current' => false,
                'imported_by' => User::query()->where('role', User::ROLE_ADMIN)->value('id'),
            ]);
        });
    }
}
