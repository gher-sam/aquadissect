<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Model;

class AdminRecordArchive extends Model
{
    use ReplicatesToDelayedClone;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['original_state' => 'array', 'archived_at' => 'datetime', 'restored_at' => 'datetime'];
    }

    public static function moduleReviewIsFresh(LearningModuleVersion $version, ?LearningModuleReview $review): bool
    {
        $cutoff = static::query()->where('resource_type', 'safety-versions')->where('record_id', $version->id)->value('review_cutoff');

        return $review !== null && $review->id > (int) $cutoff;
    }
}
