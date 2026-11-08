<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_record_archives', function (Blueprint $table): void {
            $table->id();
            $table->string('resource_type', 40);
            $table->unsignedBigInteger('record_id');
            $table->json('original_state');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('archived_at');
            $table->timestamp('restored_at')->nullable();
            $table->unsignedBigInteger('review_cutoff')->default(0);
            $table->timestamps();
            $table->unique(['resource_type', 'record_id']);
        });
        Schema::create('admin_record_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('resource_type', 40);
            $table->string('action', 12);
            $table->string('request_hash', 64);
            $table->string('reason', 500);
            $table->json('record_ids');
            $table->json('audit_details');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Administrative audit and restoration history survive application rollback.
    }
};
