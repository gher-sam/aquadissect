<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['species_package_id', 'asset_key', 'relative_path', 'mime_type', 'size_bytes', 'sha256'])]
class SpeciesPackageAsset extends Model
{
    use ReplicatesToDelayedClone;

    protected static function booted(): void
    {
        $guard = function (self $asset): void {
            $packageIds = array_values(array_unique(array_filter([
                $asset->getOriginal('species_package_id'),
                $asset->species_package_id,
            ])));

            if (SpeciesPackage::query()->whereKey($packageIds)->whereNotNull('published_at')->exists()) {
                throw new LogicException('Assets on a published species package are immutable. Import a new version instead.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SpeciesPackage::class, 'species_package_id');
    }
}
