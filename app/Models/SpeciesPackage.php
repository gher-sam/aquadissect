<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'species_id', 'version', 'schema_version', 'status', 'viewer_profile', 'minimum_app_version',
    'manifest', 'archive_path', 'checksum', 'size_bytes', 'is_current', 'imported_by', 'reviewed_by',
    'review_round', 'published_at', 'archived_at',
])]
class SpeciesPackage extends Model
{
    use ReplicatesToDelayedClone;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEW = 'review';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected static function booted(): void
    {
        static::updating(function (SpeciesPackage $package): void {
            if (! $package->getOriginal('published_at')) {
                return;
            }

            $contentFields = ['species_id', 'version', 'schema_version', 'viewer_profile', 'minimum_app_version', 'manifest', 'archive_path', 'checksum', 'size_bytes'];
            if ($package->isDirty($contentFields)) {
                throw new LogicException('Published species packages are immutable. Import a new version instead.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'is_current' => 'boolean',
            'review_round' => 'integer',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(SpeciesPackageAsset::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SpeciesPackageEvent::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(SpeciesPackageReview::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(LessonResult::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }
}
