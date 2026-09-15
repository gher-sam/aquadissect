<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['class_id', 'species_id', 'species_package_id', 'assigned_by', 'assigned_at', 'starts_at', 'due_at', 'active'])]
class SpeciesAssignment extends Model
{
    use ReplicatesToDelayedClone;

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function learningClass(): BelongsTo
    {
        return $this->belongsTo(LearningClass::class, 'class_id');
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SpeciesPackage::class, 'species_package_id');
    }
}
