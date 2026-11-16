<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('practice_completions')) {
            return;
        }

        Schema::create('practice_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('species_package_id')->constrained()->restrictOnDelete();
            $table->string('challenge_key', 120);
            $table->unsignedSmallInteger('points');
            $table->timestamp('completed_at');
            $table->timestamps();
            $table->unique(['user_id', 'class_id', 'species_package_id', 'challenge_key'], 'one_practice_reward');
        });
    }

    public function down(): void
    {
        // Retain learner rewards during application rollback.
    }
};
