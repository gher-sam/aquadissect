<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('species_packages', function (Blueprint $table): void {
            $table->unsignedInteger('review_round')->default(0)->after('reviewed_by');
        });

        Schema::create('species_package_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('species_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewer_role', 80)->default('Faculty Reviewer');
            $table->unsignedInteger('review_round')->default(0);
            $table->string('content_fingerprint', 64);
            $table->string('decision', 30);
            $table->text('notes');
            $table->string('evidence_reference', 500)->nullable();
            $table->boolean('curriculum_alignment_confirmed')->default(false);
            $table->boolean('safety_review_confirmed')->default(false);
            $table->timestamps();
            $table->index(['species_package_id', 'decision']);
        });

        $rabbitfishPackageIds = DB::table('species_packages')
            ->where('version', '1.0.0')
            ->where('status', 'draft')
            ->whereIn('species_id', DB::table('species')->where('slug', 'rabbitfish')->select('id'))
            ->pluck('id');
        foreach ($rabbitfishPackageIds as $packageId) {
            DB::table('species_packages')->where('id', $packageId)->update([
                'status' => 'review',
                'review_round' => 1,
                'reviewed_by' => null,
                'updated_at' => now(),
            ]);
            if (! DB::table('species_package_events')->where([
                'species_package_id' => $packageId,
                'event' => 'prepared_for_faculty_review',
            ])->exists()) {
                DB::table('species_package_events')->insert([
                    'species_package_id' => $packageId,
                    'user_id' => null,
                    'event' => 'prepared_for_faculty_review',
                    'metadata' => json_encode(['source' => 'species-review-milestone'], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('species_package_reviews');
        Schema::table('species_packages', function (Blueprint $table): void {
            $table->dropColumn('review_round');
        });
    }
};
