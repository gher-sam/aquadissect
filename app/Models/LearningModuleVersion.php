<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'learning_module_id', 'version', 'status', 'manifest', 'is_current', 'imported_by', 'published_at', 'archived_at',
])]
class LearningModuleVersion extends Model
{
    use ReplicatesToDelayedClone;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEW = 'review';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'is_current' => 'boolean',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if (! $version->hasPublishedHistory(true)) {
                return;
            }

            $allowed = ['status', 'is_current', 'archived_at', 'updated_at'];
            if (array_diff(array_keys($version->getDirty()), $allowed) !== []) {
                throw new LogicException('Published learning-module versions are immutable. Create a new version instead.');
            }
        });
    }

    public function hasPublishedHistory(bool $original = false): bool
    {
        if (($original ? $this->getOriginal('published_at') : $this->published_at)
            || ($original ? $this->getOriginal('status') : $this->status) === self::STATUS_PUBLISHED) {
            return true;
        }
        $archive = AdminRecordArchive::query()->where('resource_type', 'safety-versions')->where('record_id', $this->id)->first();

        return (bool) ($archive?->original_state['publishedHistory'] ?? false)
            || in_array($archive?->original_state['status'], [self::STATUS_PUBLISHED, self::STATUS_ARCHIVED], true);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(LearningModule::class, 'learning_module_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(LearningModuleAsset::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ClassModuleAssignment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(LearningModuleReview::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LearningModuleProgress::class);
    }
}
