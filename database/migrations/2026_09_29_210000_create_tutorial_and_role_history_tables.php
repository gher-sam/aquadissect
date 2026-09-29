<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutorial_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('guide_key', 80);
            $table->unsignedInteger('version');
            $table->string('step_key', 80);
            $table->timestamp('viewed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'guide_key', 'version']);
        });
        Schema::create('role_change_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('previous_role');
            $table->string('new_role');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_change_events');
        Schema::dropIfExists('tutorial_progress');
    }
};
