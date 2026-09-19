<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_modules', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('title', 160);
            $table->string('category', 60);
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('learning_module_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learning_module_id')->constrained()->cascadeOnDelete();
            $table->string('version', 40);
            $table->string('status', 20)->default('draft');
            $table->json('manifest');
            $table->boolean('is_current')->default(false);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['learning_module_id', 'version']);
            $table->index(['status', 'is_current']);
        });

        Schema::create('learning_module_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learning_module_version_id')->constrained()->cascadeOnDelete();
            $table->string('asset_key', 120);
            $table->string('relative_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['learning_module_version_id', 'asset_key'], 'learning_module_asset_key_unique');
        });

        Schema::create('class_module_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('learning_module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_module_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('required')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamp('assigned_at');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
            $table->unique(['class_id', 'learning_module_id'], 'class_learning_module_unique');
        });

        Schema::create('learning_module_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('learning_module_version_id')->constrained()->cascadeOnDelete();
            $table->json('completed_step_ids');
            $table->string('source', 20)->default('online');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'class_id', 'learning_module_version_id'], 'learning_module_progress_unique');
        });

        Schema::create('learning_module_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('learning_module_version_id')->constrained()->cascadeOnDelete();
            $table->uuid('attempt_uuid');
            $table->unsignedInteger('score');
            $table->unsignedInteger('total');
            $table->string('source', 20)->default('online');
            $table->timestamp('completed_at');
            $table->timestamp('submitted_at');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'attempt_uuid']);
        });

        Schema::create('learning_module_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learning_module_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewer_role', 80)->default('Faculty Reviewer');
            $table->string('decision', 30);
            $table->text('notes');
            $table->string('evidence_reference')->nullable();
            $table->timestamps();
        });

        Schema::create('learning_module_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('learning_module_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at');
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['class_id', 'user_id', 'learning_module_id'], 'learning_module_override_lookup');
        });

        Schema::create('media_prompt_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learning_module_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tool_key', 100);
            $table->string('media_type', 30);
            $table->text('prompt_text');
            $table->json('options');
            $table->timestamp('attached_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_prompt_records');
        Schema::dropIfExists('learning_module_overrides');
        Schema::dropIfExists('learning_module_reviews');
        Schema::dropIfExists('learning_module_attempts');
        Schema::dropIfExists('learning_module_progress');
        Schema::dropIfExists('class_module_assignments');
        Schema::dropIfExists('learning_module_assets');
        Schema::dropIfExists('learning_module_versions');
        Schema::dropIfExists('learning_modules');
    }
};
