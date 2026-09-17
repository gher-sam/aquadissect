<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->timestamps();
        });

        Schema::table('classes', function (Blueprint $table): void {
            $table->foreignId('course_id')->nullable()->constrained('courses')->restrictOnDelete();
            $table->string('academic_year', 20)->nullable();
            $table->string('semester', 30)->nullable();
            $table->string('section_code', 40)->nullable();
            $table->unique(['course_id', 'academic_year', 'semester', 'section_code'], 'classes_offering_unique');
        });

        Schema::table('lesson_results', function (Blueprint $table): void {
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
        });

        Schema::create('account_activation_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('roster_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('action', 50);
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_events');
        Schema::dropIfExists('account_activation_tokens');
        Schema::table('lesson_results', fn (Blueprint $table) => $table->dropConstrainedForeignId('class_id'));
        Schema::table('classes', function (Blueprint $table): void {
            $table->dropUnique('classes_offering_unique');
            $table->dropConstrainedForeignId('course_id');
            $table->dropColumn(['academic_year', 'semester', 'section_code']);
        });
        Schema::dropIfExists('courses');
    }
};
