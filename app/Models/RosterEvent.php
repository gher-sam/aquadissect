<?php

namespace App\Models;

use App\Models\Concerns\ReplicatesToDelayedClone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_id', 'user_id', 'class_id', 'action', 'details'])]
class RosterEvent extends Model
{
    use ReplicatesToDelayedClone;

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
