<?php

namespace App\Services;

use App\Models\LearningModuleVersion;
use App\Models\SpeciesPackage;
use Database\Seeders\LearningModuleSeeder;
use Database\Seeders\SpeciesCatalogSeeder;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\File;
use RuntimeException;

class AutomaticDatabaseSetup
{
    private static bool $checked = false;

    public function ensureCurrent(Migrator $migrator): void
    {
        if (self::$checked || ! config('aquadissect.auto_migrate')) {
            return;
        }
        self::$checked = true;

        $lockPath = storage_path('framework/cache/aquadissect-database-setup.lock');
        File::ensureDirectoryExists(dirname($lockPath));
        $lock = fopen($lockPath, 'c+');
        if (! is_resource($lock) || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('AquaDissect could not obtain its automatic database setup lock.');
        }

        try {
            $this->migrateConnection($migrator, config('database.default'));
            if (config('delayed-clone.enabled')) {
                $clone = config('delayed-clone.connection');
                $clonePath = config("database.connections.{$clone}.database");
                if (is_string($clonePath) && $clonePath !== ':memory:' && ! File::exists($clonePath)) {
                    File::ensureDirectoryExists(dirname($clonePath));
                    File::put($clonePath, '');
                }
                $this->migrateConnection($migrator, $clone);
            }
            app(CourseProvisioner::class)->ensure();
            $tilapiaVideoPackageMissing = ! SpeciesPackage::query()
                ->whereHas('species', fn ($query) => $query->where('slug', 'tilapia'))
                ->where('version', '1.1.0')
                ->exists();
            $rabbitfishPackageMissing = ! SpeciesPackage::query()
                ->whereHas('species', fn ($query) => $query->where('slug', 'rabbitfish'))
                ->where('version', '1.0.0')
                ->exists();
            if ($tilapiaVideoPackageMissing || $rabbitfishPackageMissing) {
                app(SpeciesCatalogSeeder::class)->run();
            }
            if (! LearningModuleVersion::query()->where('version', '0.1.0')
                ->whereHas('module', fn ($query) => $query->where('slug', 'lab-safety-tools'))->exists()) {
                app(LearningModuleSeeder::class)->run();
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function migrateConnection(Migrator $migrator, string $connection): void
    {
        $migrator->usingConnection($connection, function () use ($migrator): void {
            if (! $migrator->repositoryExists()) {
                $migrator->getRepository()->createRepository();
            }
            $migrator->run(database_path('migrations'), ['pretend' => false, 'step' => false]);
        });
    }
}
