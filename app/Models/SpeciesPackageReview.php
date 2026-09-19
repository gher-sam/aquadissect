<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'species_package_id', 'reviewer_id', 'reviewer_role', 'review_round', 'content_fingerprint', 'decision', 'notes', 'evidence_reference',
    'curriculum_alignment_confirmed', 'safety_review_confirmed',
])]
class SpeciesPackageReview extends Model
{
    use ReplicatesToDelayedClone;

    protected function casts(): array
    {
        return [
            'curriculum_alignment_confirmed' => 'boolean',
            'safety_review_confirmed' => 'boolean',
            'review_round' => 'integer',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SpeciesPackage::class, 'species_package_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
