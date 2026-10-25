<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_setup_challenges', function (Blueprint $table): void {
            $table->string('method', 12)->default('email');
        });
    }

    public function down(): void
    {
        Schema::table('student_setup_challenges', function (Blueprint $table): void {
            $table->dropColumn('method');
        });
    }
};
