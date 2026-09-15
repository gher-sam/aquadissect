<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('lesson_key');
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('total');
            $table->timestamp('completed_at');
            $table->timestamps();
            $table->index(['user_id', 'lesson_key', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_results');
    }
};
