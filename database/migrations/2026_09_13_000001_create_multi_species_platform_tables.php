<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('species', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('common_name');
            $table->string('scientific_name')->nullable();
            $table->string('taxonomic_group', 80);
            $table->string('activity_mode', 40)->default('virtual_dissection');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('species_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('species_id')->constrained('species')->cascadeOnDelete();
            $table->string('version', 40);
            $table->string('schema_version', 20)->default('1.0');
            $table->string('status', 20)->default('draft');
            $table->string('viewer_profile', 40)->default('layered-2d');
            $table->string('minimum_app_version', 40)->default('1.0.0');
            $table->json('manifest');
            $table->string('archive_path')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->boolean('is_current')->default(false);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['species_id', 'version']);
            $table->index(['status', 'is_current']);
        });

        Schema::create('species_package_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('species_package_id')->constrained()->cascadeOnDelete();
            $table->string('asset_key');
            $table->string('relative_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->timestamps();
            $table->unique(['species_package_id', 'asset_key']);
        });

        Schema::create('classes', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('class_instructors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['class_id', 'user_id']);
        });

        Schema::create('class_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['class_id', 'user_id']);
        });

        Schema::create('species_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('species_id')->constrained('species')->cascadeOnDelete();
            $table->foreignId('species_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['class_id', 'species_id']);
        });

        Schema::create('species_package_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('species_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('lesson_results', function (Blueprint $table): void {
            $table->foreignId('species_package_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->uuid('attempt_uuid')->nullable()->after('lesson_key');
            $table->string('package_version', 40)->nullable()->after('attempt_uuid');
            $table->string('source', 20)->default('online')->after('total');
            $table->timestamp('submitted_at')->nullable()->after('completed_at');
            $table->timestamp('synced_at')->nullable()->after('submitted_at');
            $table->unique(['user_id', 'attempt_uuid']);
        });

        $now = now();
        $speciesId = DB::table('species')->insertGetId([
            'slug' => 'tilapia',
            'common_name' => 'Nile Tilapia',
            'scientific_name' => 'Oreochromis niloticus',
            'taxonomic_group' => 'Bony fish',
            'activity_mode' => 'virtual_dissection',
            'description' => 'The flagship AquaDissect guided anatomy specimen.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $packageId = DB::table('species_packages')->insertGetId([
            'species_id' => $speciesId,
            'version' => '1.0.0',
            'schema_version' => '1.0',
            'status' => 'published',
            'viewer_profile' => 'layered-2d+3d',
            'minimum_app_version' => '1.0.0',
            'manifest' => json_encode(['schemaVersion' => '1.0', 'packageVersion' => '1.0.0', 'lessonKey' => 'tilapia-guided-dissection'], JSON_THROW_ON_ERROR),
            'size_bytes' => 0,
            'is_current' => true,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('lesson_results')->where('lesson_key', 'tilapia-guided-dissection')->update([
            'species_package_id' => $packageId,
            'package_version' => '1.0.0',
            'source' => 'legacy',
            'submitted_at' => DB::raw('completed_at'),
            'synced_at' => DB::raw('completed_at'),
        ]);
        DB::table('lesson_results')
            ->where('lesson_key', 'tilapia-guided-dissection')
            ->whereNull('attempt_uuid')
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $resultId) => DB::table('lesson_results')->where('id', $resultId)->update([
                'attempt_uuid' => (string) Str::uuid(),
            ]));

        $classCodes = DB::table('users')
            ->whereNotNull('class_code')
            ->where('class_code', '<>', '')
            ->distinct()
            ->pluck('class_code')
            ->push('FISHBIO-101')
            ->unique();
        foreach ($classCodes as $classCode) {
            $classId = DB::table('classes')->insertGetId([
                'code' => $classCode,
                'name' => Str::headline(strtolower($classCode)),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $studentIds = DB::table('users')->where('class_code', $classCode)->pluck('id');
            foreach ($studentIds as $studentId) {
                DB::table('class_enrollments')->insert([
                    'class_id' => $classId,
                    'user_id' => $studentId,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('species_assignments')->insert([
                'class_id' => $classId,
                'species_id' => $speciesId,
                'species_package_id' => $packageId,
                'assigned_by' => null,
                'assigned_at' => $now,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('lesson_results', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'attempt_uuid']);
            $table->dropConstrainedForeignId('species_package_id');
            $table->dropColumn(['attempt_uuid', 'package_version', 'source', 'submitted_at', 'synced_at']);
        });

        Schema::dropIfExists('species_package_events');
        Schema::dropIfExists('species_assignments');
        Schema::dropIfExists('class_enrollments');
        Schema::dropIfExists('class_instructors');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('species_package_assets');
        Schema::dropIfExists('species_packages');
        Schema::dropIfExists('species');
    }
};
