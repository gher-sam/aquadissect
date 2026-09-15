<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'common_name', 'scientific_name', 'taxonomic_group', 'activity_mode', 'description'])]
class Species extends Model
{
    use ReplicatesToDelayedClone;

    public function packages(): HasMany
    {
        return $this->hasMany(SpeciesPackage::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SpeciesAssignment::class);
    }
}
