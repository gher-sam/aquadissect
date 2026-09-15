<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['species_package_id', 'user_id', 'event', 'metadata'])]
class SpeciesPackageEvent extends Model
{
    use ReplicatesToDelayedClone;

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SpeciesPackage::class, 'species_package_id');
    }
}
