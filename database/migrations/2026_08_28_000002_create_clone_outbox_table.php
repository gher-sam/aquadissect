<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clone_outbox', function (Blueprint $table): void {
            $table->id();
            $table->string('table_name');
            $table->string('key_name')->default('id');
            $table->string('record_key');
            $table->string('operation', 10);
            $table->json('payload')->nullable();
            $table->timestamp('available_at')->index();
            $table->timestamp('applied_at')->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['applied_at', 'available_at', 'id'], 'clone_outbox_ready_index');
            $table->index(['table_name', 'record_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clone_outbox');
    }
};
