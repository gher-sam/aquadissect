<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes');
            $table->foreignId('created_by')->constrained('users');
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
        });
        Schema::create('workspace_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('event_key', 64);
            $table->string('type', 80);
            $table->string('summary');
            $table->string('destination', 80);
            $table->foreignId('class_id')->nullable()->constrained('classes');
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'event_key']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_notifications');
        Schema::dropIfExists('calendar_events');
    }
};
