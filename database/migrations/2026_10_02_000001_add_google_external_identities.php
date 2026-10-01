<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SQLite must disable foreign keys outside a transaction during its table rebuild.
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('password')->nullable()->change();
            });
        });
        Schema::create('external_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('subject', 255);
            $table->string('verified_email');
            $table->timestamp('verified_at');
            $table->timestamps();
            $table->unique(['provider', 'subject']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        // Retain nullable passwords: Google-only accounts have no password hash.
        Schema::dropIfExists('external_identities');
    }
};
