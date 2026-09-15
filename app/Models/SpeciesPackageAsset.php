<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['species_package_id', 'asset_key', 'relative_path', 'mime_type', 'size_bytes', 'sha256'])]
class SpeciesPackageAsset extends Model
{
    use ReplicatesToDelayedClone;

    public function package(): BelongsTo
    {
        return $this->belongsTo(SpeciesPackage::class, 'species_package_id');
    }
}
