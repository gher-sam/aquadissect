<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class AssessmentStudy extends Model
{
    use ReplicatesToDelayedClone;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['pre_questions' => 'array', 'post_questions' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function ($study) {
            if ($study->isDirty(['class_id', 'species_package_id', 'author_id', 'title', 'instrument_version', 'pre_questions', 'post_questions'])) {
                throw new LogicException('Create a new instrument version instead of changing assessment content.');
            }
        });
        static::deleting(fn () => throw new LogicException('Assessment history must be retained.'));
    }
}
