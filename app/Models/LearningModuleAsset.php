<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'learning_module_version_id', 'asset_key', 'relative_path', 'mime_type', 'size_bytes', 'sha256', 'metadata',
])]
class LearningModuleAsset extends Model
{
    use ReplicatesToDelayedClone;

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    protected static function booted(): void
    {
        $guard = function (self $asset): void {
            if ($asset->version()->where('status', LearningModuleVersion::STATUS_PUBLISHED)->exists()) {
                throw new LogicException('Assets on a published learning-module version are immutable.');
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LearningModuleVersion::class, 'learning_module_version_id');
    }
}
