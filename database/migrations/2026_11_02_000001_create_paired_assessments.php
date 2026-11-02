<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_studies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $t->foreignId('species_package_id')->constrained()->restrictOnDelete();
            $t->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('title', 160);
            $t->string('instrument_version', 80);
            $t->json('pre_questions');
            $t->json('post_questions');
            $t->string('status', 20)->default('draft');
            $t->text('approval_evidence')->nullable();
            $t->timestamps();
        });
        Schema::create('assessment_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assessment_study_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('phase', 10);
            $t->uuid('attempt_uuid');
            $t->json('answers');
            $t->unsignedSmallInteger('score');
            $t->unsignedSmallInteger('total');
            $t->timestamp('submitted_at');
            $t->timestamp('voided_at')->nullable();
            $t->timestamps();
            $t->unique(['assessment_study_id', 'user_id', 'phase'], 'one_scored_assessment_phase');
            $t->unique(['user_id', 'attempt_uuid']);
        });
        Schema::create('assessment_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assessment_study_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('action', 40);
            $t->json('metadata');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        // Academic records are retained during application rollback.
    }
};
